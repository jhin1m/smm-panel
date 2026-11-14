<?php

namespace App\Services;

use App\Filament\Pages\ChatInbox;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chat khách ↔ admin. Nơi DUY NHẤT chứa định nghĩa "chưa đọc":
 * - Admin: tin customer có id > admin_last_read_id.
 * - Khách: tin admin có id > customer_last_read_id.
 */
class ChatService
{
    public const MAX_LENGTH = 2000;

    public function __construct(private AdminNotifier $notifier) {}

    /** Khách gửi tin. Tin đầu tiên của chuỗi chưa đọc → báo Telegram đúng 1 lần (claim nguyên tử). */
    public function postCustomerMessage(User $user, string $body): ChatMessage
    {
        $body = $this->clean($body);

        return DB::transaction(function () use ($user, $body) {
            // insertOrIgnore: 2 tin đầu gửi gần như đồng thời không vỡ unique(user_id).
            DB::table('chat_threads')->insertOrIgnore(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            $thread = ChatThread::where('user_id', $user->id)->firstOrFail();

            $msg = $thread->messages()->create(['sender_type' => ChatMessage::CUSTOMER, 'sender_id' => $user->id, 'body' => $body]);
            // Chỉ tiến, không lùi (tin admin có id lớn hơn có thể vừa ghi song song).
            ChatThread::whereKey($thread->id)
                ->where(fn ($q) => $q->whereNull('last_message_id')->orWhere('last_message_id', '<', $msg->id))
                ->update(['last_message_id' => $msg->id, 'last_message_at' => $msg->created_at]);

            $claimed = ChatThread::whereKey($thread->id)->whereNull('admin_notified_at')->update(['admin_notified_at' => now()]);
            if ($claimed === 1) {
                // Job afterCommit: rollback → không gửi.
                // Tin gửi admin luôn theo ngôn ngữ mặc định của site, không theo locale của khách.
                $this->notifier->sendHtml(__('telegram.chat_new_message', [
                    'name' => e($user->name),
                    'email' => e($user->email),
                    'body' => e(Str::limit($body, 500)),
                    'url' => e(ChatInbox::getUrl(['thread' => $thread->id], panel: 'admin')),
                ], config('app.default_locale')));
            }

            return $msg;
        });
    }

    public function postAdminMessage(int $threadId, User $admin, string $body): ChatMessage
    {
        $body = $this->clean($body);

        return DB::transaction(function () use ($threadId, $admin, $body) {
            $thread = ChatThread::whereKey($threadId)->lockForUpdate()->firstOrFail();
            $msg = $thread->messages()->create(['sender_type' => ChatMessage::ADMIN, 'sender_id' => $admin->id, 'body' => $body]);

            // Admin trả lời = đã đọc hết tới tin mới nhất.
            $newer = $msg->id > (int) $thread->last_message_id;
            $thread->update([
                'last_message_id' => $newer ? $msg->id : $thread->last_message_id,
                'last_message_at' => $newer ? $msg->created_at : $thread->last_message_at,
                'admin_last_read_id' => max($thread->admin_last_read_id, $msg->id),
                'admin_notified_at' => null,
            ]);

            return $msg;
        });
    }

    public function markReadByCustomer(User $user, int $upToId): void
    {
        ChatThread::where('user_id', $user->id)
            ->where('customer_last_read_id', '<', $upToId)
            ->update(['customer_last_read_id' => $upToId]);
    }

    /** Chỉ gọi khi admin chọn thread / focus ô trả lời — KHÔNG gọi từ poll. Reset cờ báo Telegram. */
    public function markReadByAdmin(int $threadId, int $upToId): void
    {
        ChatThread::whereKey($threadId)->update([
            'admin_last_read_id' => DB::raw('GREATEST(admin_last_read_id, '.(int) $upToId.')'),
            'admin_notified_at' => null,
        ]);
    }

    /** Số thread có tin khách chưa đọc — badge nav, ChatInbox, dashboard dùng chung. */
    public function unreadThreadsForAdmin(): int
    {
        return ChatThread::query()
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('chat_messages')
                ->whereColumn('chat_messages.thread_id', 'chat_threads.id')
                ->where('chat_messages.sender_type', ChatMessage::CUSTOMER)
                ->whereColumn('chat_messages.id', '>', 'chat_threads.admin_last_read_id'))
            ->count();
    }

    public function unreadCountForAdmin(int $threadId): int
    {
        $thread = ChatThread::find($threadId);

        return $thread ? $thread->messages()
            ->where('sender_type', ChatMessage::CUSTOMER)
            ->where('id', '>', $thread->admin_last_read_id)->count() : 0;
    }

    /** @return array<int, int> thread_id => số tin chưa đọc (cho danh sách inbox, 1 query) */
    public function unreadCountsForAdmin(): array
    {
        return ChatMessage::query()
            ->join('chat_threads', 'chat_threads.id', '=', 'chat_messages.thread_id')
            ->where('chat_messages.sender_type', ChatMessage::CUSTOMER)
            ->whereColumn('chat_messages.id', '>', 'chat_threads.admin_last_read_id')
            ->groupBy('chat_messages.thread_id')
            ->selectRaw('chat_messages.thread_id, count(*) as n')
            ->pluck('n', 'thread_id')->map(fn ($n) => (int) $n)->all();
    }

    public function unreadCountForCustomer(User $user): int
    {
        $thread = ChatThread::where('user_id', $user->id)->first();

        return $thread ? $thread->messages()
            ->where('sender_type', ChatMessage::ADMIN)
            ->where('id', '>', $thread->customer_last_read_id)->count() : 0;
    }

    private function clean(string $body): string
    {
        $body = trim($body);

        if ($body === '' || mb_strlen($body) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(__('chat.length', ['max' => self::MAX_LENGTH]));
        }

        return $body;
    }
}
