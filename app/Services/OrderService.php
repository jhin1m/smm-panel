<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TxType;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\StaleOrderState;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Nơi duy nhất tạo đơn và đổi trạng thái/tiến độ đơn.
 * Thao tác admin nhận expected_status + expected_done đọc lúc mở modal → lệch bản ghi đã khoá = StaleOrderState.
 */
class OrderService
{
    public function __construct(
        private WalletService $wallet,
        private PricingService $pricing,
        private TierService $tiers,
    ) {}

    /**
     * Tạo đơn + trừ tiền nguyên tử. Giá tính lại hoàn toàn ở server.
     *
     * @param  array{link?: mixed, quantity?: mixed, note?: mixed}  $data
     */
    public function place(User $user, int $serviceId, array $data): Order
    {
        return DB::transaction(function () use ($user, $serviceId, $data) {
            // Khoá user TRƯỚC khi INSERT order: INSERT có FK tới users lấy KEY SHARE; khoá sau → deadlock khi đặt song song.
            $u = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($u->isLocked()) {
                throw ValidationException::withMessages(['service' => __('orders.errors.account_locked')]);
            }

            $service = Service::whereKey($serviceId)->orderable()->first();
            if (! $service) {
                throw ValidationException::withMessages(['service' => __('orders.errors.service_unavailable')]);
            }

            $valid = Validator::make($data, [
                'link' => ['required', 'string', 'max:500', 'url:http,https'],
                'quantity' => ['required', 'integer', "min:{$service->min_quantity}", "max:{$service->max_quantity}"],
                'note' => ['nullable', 'string', 'max:500'],
            ])->validate();

            $qty = (int) $valid['quantity'];
            $pct = $this->tiers->discountPercent($u);

            $order = Order::create([
                'user_id' => $u->id,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'unit' => $service->unit,
                'unit_price' => $service->price,
                'discount_percent' => $pct,
                'link' => trim($valid['link']),
                'note' => filled($valid['note'] ?? null) ? trim($valid['note']) : null,
                'quantity' => $qty,
                'done' => 0,
                'total' => $this->pricing->total($service->price, $service->unit, $qty, $pct),
                'status' => OrderStatus::Pending,
            ]);

            if ($order->total > 0) {
                // InsufficientBalance → rollback cả đơn.
                $this->wallet->debit($u, $order->total, TxType::Order, "order:{$order->id}", $order);
            }

            return $order;
        }, attempts: 3);
    }

    public function start(Order $order, OrderStatus $expectedStatus, int $expectedDone): Order
    {
        return $this->transition($order, $expectedStatus, $expectedDone, function (Order $o) {
            $this->assertTransition($o->status, OrderStatus::Processing);
            $o->update(['status' => OrderStatus::Processing]);
        });
    }

    /** Cập nhật tiến độ (chỉ tăng). Đủ số lượng không tự hoàn thành — admin bấm Hoàn thành. */
    public function updateProgress(Order $order, int $done, OrderStatus $expectedStatus, int $expectedDone): Order
    {
        return $this->transition($order, $expectedStatus, $expectedDone, function (Order $o) use ($done) {
            if (! in_array($o->status, [OrderStatus::Pending, OrderStatus::Processing], true)) {
                throw new InvalidOrderTransition($o->status, $o->status);
            }
            $this->assertDone($o, $done, allowDecrease: false);
            $o->update(['done' => $done]);
        });
    }

    public function complete(Order $order, OrderStatus $expectedStatus, int $expectedDone): Order
    {
        return $this->transition($order, $expectedStatus, $expectedDone, function (Order $o) {
            $this->assertTransition($o->status, OrderStatus::Completed);
            $o->update(['status' => OrderStatus::Completed, 'done' => $o->quantity]);
        });
    }

    /**
     * Hoàn phần chưa chạy về số dư, tối đa 1 lần/đơn (khoá đơn + kiểm trạng thái + unique key refund:order:{id}).
     * Từ completed: cho nhập done thực tế < qty (dịch vụ bị tụt) — ngoại lệ có chủ đích, bắt buộc lý do.
     */
    public function refund(Order $order, int $done, OrderStatus $expectedStatus, int $expectedDone, User $admin, ?string $reason = null): Order
    {
        $reason = filled($reason) ? trim($reason) : null;

        return $this->transition($order, $expectedStatus, $expectedDone, function (Order $o) use ($done, $admin, $reason) {
            $this->assertTransition($o->status, OrderStatus::Refunded);

            if ($o->status === OrderStatus::Completed) {
                if ($reason === null) {
                    throw ValidationException::withMessages(['reason' => __('orders.errors.refund_reason_required')]);
                }
                $this->assertDone($o, $done, allowDecrease: true);
                if ($done >= $o->quantity) {
                    throw ValidationException::withMessages(['done' => __('orders.errors.refund_already_complete')]);
                }
            } else {
                $this->assertDone($o, $done, allowDecrease: false);
            }

            $amount = $this->pricing->refundAmount($o->total, $o->quantity, $done);
            $o->update(['status' => OrderStatus::Refunded, 'done' => $done, 'refunded_amount' => $amount, 'refunded_at' => now()]);

            if ($amount > 0) {
                $this->wallet->credit($o->user, $amount, TxType::Refund, "refund:order:{$o->id}", $o, actor: $admin, description: $reason);
            }
        });
    }

    /** Khuôn chung: khoá đơn (đơn trước, user sau) → kiểm expected_* → chạy thay đổi. */
    private function transition(Order $order, OrderStatus $expectedStatus, int $expectedDone, callable $change): Order
    {
        return DB::transaction(function () use ($order, $expectedStatus, $expectedDone, $change) {
            $o = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($o->status !== $expectedStatus || $o->done !== $expectedDone) {
                throw new StaleOrderState;
            }

            $change($o);

            return $o;
        }, attempts: 3);
    }

    private function assertTransition(OrderStatus $from, OrderStatus $to): void
    {
        if (! $from->canTransitionTo($to)) {
            throw new InvalidOrderTransition($from, $to);
        }
    }

    private function assertDone(Order $o, int $done, bool $allowDecrease): void
    {
        if ($done < 0 || $done > $o->quantity) {
            throw ValidationException::withMessages(['done' => __('orders.errors.progress_range', ['max' => $o->quantity])]);
        }

        if (! $allowDecrease && $done < $o->done) {
            throw ValidationException::withMessages(['done' => __('orders.errors.progress_increase_only')]);
        }
    }
}
