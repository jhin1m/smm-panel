<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
| Canh gác hạ tầng song song: nếu đỏ, mọi test song song khác đều vô nghĩa.
*/
it('tiến trình con dùng đúng DB test và thấy dữ liệu cha đã seed', function () {
    $user = User::factory()->create(['email' => 'harness@example.com']);
    $id = $user->id;

    $results = runParallel([
        fn () => [DB::connection()->getDatabaseName(), User::find($id)?->email],
        fn () => [DB::connection()->getDatabaseName(), User::find($id)?->email],
    ], delay: 0.5);

    expect($results)->each->toBe(['smm_panel_test', 'harness@example.com']);
});

it('exception trong tiến trình con trả về tên lớp', function () {
    $results = runParallel([
        fn () => throw new InvalidArgumentException('boom'),
        fn () => 'ok',
    ], delay: 0.5);

    expect($results)->toBe([InvalidArgumentException::class, 'ok']);
});
