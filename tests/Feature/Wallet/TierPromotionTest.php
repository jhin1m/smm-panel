<?php

use App\Enums\Tier;
use App\Enums\TxType;
use App\Models\User;
use App\Services\TierService;
use App\Services\WalletService;
use App\Settings\TierSettings;
use Illuminate\Support\Facades\DB;

function depositAndPromote(User $u, int $amount, string $key): void
{
    DB::transaction(function () use ($u, $amount, $key) {
        app(WalletService::class)->credit($u, $amount, TxType::Topup, $key);
        app(TierService::class)->promoteIfEligible($u->id);
    });
}

it('4,9tr + nạp 200k → Đại lý', function () {
    $u = User::factory()->create();
    depositAndPromote($u, 4_900_000, 'a');
    expect($u->fresh()->tier)->toBe(Tier::Member);

    depositAndPromote($u, 200_000, 'b');
    expect($u->fresh()->tier)->toBe(Tier::Agency)->and($u->fresh()->tier_changed_at)->not->toBeNull();
});

it('admin hạ tay về member (tier_manual) → nạp tiếp vẫn member', function () {
    $u = User::factory()->create();
    depositAndPromote($u, 6_000_000, 'a');
    $u->forceFill(['tier' => Tier::Member, 'tier_manual' => true])->save();

    depositAndPromote($u, 100_000, 'b');

    expect($u->fresh()->tier)->toBe(Tier::Member);
});

it('agency nạp thêm → không đổi', function () {
    $u = User::factory()->create();
    depositAndPromote($u, 5_000_000, 'a');
    $changedAt = $u->fresh()->tier_changed_at;
    $this->travel(1)->minutes();

    depositAndPromote($u, 1_000_000, 'b');

    expect($u->fresh()->tier)->toBe(Tier::Agency)->and($u->fresh()->tier_changed_at->equalTo($changedAt))->toBeTrue();
});

it('hạ ngưỡng 5tr → 3tr: member 3,5tr được nâng ngay, tier_manual giữ nguyên', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    depositAndPromote($a, 3_500_000, 'a');
    depositAndPromote($b, 3_500_000, 'b');
    $b->forceFill(['tier_manual' => true])->save();

    $s = app(TierSettings::class);
    $s->agency_threshold = 3_000_000;
    $s->save();

    expect(app(TierService::class)->promoteAllEligible())->toBe(1)
        ->and($a->fresh()->tier)->toBe(Tier::Agency)
        ->and($b->fresh()->tier)->toBe(Tier::Member);
});

it('% giảm theo cấp', function () {
    $m = User::factory()->create();
    $a = User::factory()->create();
    $a->forceFill(['tier' => Tier::Agency])->save();

    expect(app(TierService::class)->discountPercent($m))->toBe(0)
        ->and(app(TierService::class)->discountPercent($a))->toBe(8);
});
