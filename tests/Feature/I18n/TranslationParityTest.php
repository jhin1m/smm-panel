<?php

use Illuminate\Support\Arr;

/** Tập key (dạng dot, đệ quy) của một file lang. */
function langKeys(string $file): array
{
    $flatten = function (array $arr, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($arr as $k => $v) {
            $path = $prefix === '' ? (string) $k : "{$prefix}.{$k}";
            if (is_array($v)) {
                array_push($keys, ...$flatten($v, $path));
            } else {
                $keys[] = $path;
            }
        }

        return $keys;
    };

    $keys = $flatten(require $file);
    sort($keys);

    return $keys;
}

it('mỗi file lang/vi có file cùng tên ở lang/en với đúng tập key (hai chiều)', function () {
    $files = array_map('basename', glob(lang_path('vi/*.php')));
    expect($files)->not->toBeEmpty();

    foreach ($files as $name) {
        expect(is_file(lang_path("en/{$name}")))->toBeTrue("thiếu lang/en/{$name}");

        $vi = langKeys(lang_path("vi/{$name}"));
        $en = langKeys(lang_path("en/{$name}"));

        expect(array_values(array_diff($vi, $en)))->toBe([], "lang/en/{$name} thiếu key so với vi")
            ->and(array_values(array_diff($en, $vi)))->toBe([], "lang/en/{$name} thừa key so với vi");
    }
});

it('không có file lang/en nào thiếu bản vi', function () {
    $vi = array_map('basename', glob(lang_path('vi/*.php')));
    $en = array_map('basename', glob(lang_path('en/*.php')));

    expect(array_values(array_diff($en, $vi)))->toBe([]);
});

it('placeholder :name của vi và en khớp nhau ở mọi key', function () {
    $placeholders = fn (mixed $v): array => (function () use ($v) {
        preg_match_all('/:[a-z_]+/', (string) $v, $m);
        $p = array_unique($m[0]);
        sort($p);

        return array_values($p);
    })();

    foreach (array_map('basename', glob(lang_path('vi/*.php'))) as $name) {
        $vi = Arr::dot(require lang_path("vi/{$name}"));
        $en = Arr::dot(require lang_path("en/{$name}"));

        foreach ($vi as $key => $text) {
            if ($name === 'validation.php' && str_starts_with($key, 'attributes.')) {
                continue;
            }
            expect($placeholders($en[$key] ?? ''))->toBe($placeholders($text), "{$name}:{$key}");
        }
    }
});
