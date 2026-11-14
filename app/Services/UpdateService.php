<?php

namespace App\Services;

use App\Exceptions\UpdateRejected;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Phía app của tính năng cập nhật: đọc trạng thái do container updater ghi vào thư mục chung
 * (heartbeat, status.json) và gửi yêu cầu bằng inbox/request.json. App KHÔNG chạm docker; chuỗi version là
 * đầu vào duy nhất updater nhận từ app, và updater tự kiểm lại mọi điều kiện.
 */
class UpdateService
{
    public const SEMVER = '/^(\d{1,6})\.(\d{1,6})\.(\d{1,6})(?:-rc\.(\d{1,6}))?$/D';

    /** Thư mục con duy nhất app ghi được; thư mục gốc thuộc root (updater ghi status/heartbeat/history). */
    public const INBOX = 'inbox';

    private const CACHE_KEY = 'updates.latest_release';

    private const FAILURE_SENTINEL = 'unavailable';

    /** Chờ lại sau khi gọi GitHub lỗi (giây), tránh gọi lại ở mỗi lần render. */
    private const FAILURE_BACKOFF = 300;

    public function path(string $file = ''): string
    {
        $dir = rtrim((string) config('updates.path'), '/');

        return $file === '' ? $dir : "{$dir}/{$file}";
    }

    /** Phiên bản đang chạy (file VERSION trong image), bỏ tiền tố "v" nếu có. */
    public function currentVersion(): string
    {
        return ltrim((string) config('app.version'), 'vV');
    }

    /** @return array{0:int,1:int,2:int,3:?int}|null [major, minor, patch, rc|null]; null nếu không đúng định dạng. */
    public static function parse(string $version): ?array
    {
        if (! preg_match(self::SEMVER, $version, $m)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2], (int) $m[3], isset($m[4]) ? (int) $m[4] : null];
    }

    /** $a > $b theo semver; bản ổn định lớn hơn mọi rc cùng X.Y.Z. Chuỗi không hợp lệ: false. */
    public static function isNewer(string $a, string $b): bool
    {
        $pa = self::parse($a);
        $pb = self::parse($b);
        if ($pa === null || $pb === null) {
            return false;
        }

        foreach ([0, 1, 2] as $i) {
            if ($pa[$i] !== $pb[$i]) {
                return $pa[$i] > $pb[$i];
            }
        }

        if ($pa[3] === $pb[3]) {
            return false;
        }

        return $pa[3] === null || ($pb[3] !== null && $pa[3] > $pb[3]);
    }

    public static function isPrerelease(string $version): bool
    {
        return (self::parse($version)[3] ?? null) !== null;
    }

    /**
     * Bản phát hành mới nhất: stable (hoặc cả rc khi bật UPDATER_ALLOW_PRERELEASE), cache 1 giờ.
     * Lỗi mạng/API trả null và được nhớ ngắn hạn.
     *
     * @return array{version:string,name:string,body:string,url:string,published_at:?string,prerelease:bool}|null
     */
    public function latestRelease(): ?array
    {
        $key = $this->cacheKey();
        $cached = Cache::get($key);
        if ($cached === self::FAILURE_SENTINEL) {
            return null;
        }
        if (is_array($cached)) {
            return $cached;
        }

        $release = $this->fetchLatestRelease();
        if ($release === null) {
            Cache::put($key, self::FAILURE_SENTINEL, self::FAILURE_BACKOFF);

            return null;
        }

        Cache::put($key, $release, (int) config('updates.release_cache_ttl'));

        return $release;
    }

    public function forgetLatestRelease(): void
    {
        Cache::forget($this->cacheKey());
    }

    /** Bật/tắt bản rc đổi kết quả nên không dùng chung một ô cache. */
    private function cacheKey(): string
    {
        return self::CACHE_KEY.(config('updates.allow_prerelease') ? '.pre' : '');
    }

    private function fetchLatestRelease(): ?array
    {
        $repo = config('updates.repo');
        $allowPre = (bool) config('updates.allow_prerelease');

        try {
            $client = Http::acceptJson()->timeout(8)->withHeaders(['X-GitHub-Api-Version' => '2022-11-28']);
            // /releases/latest chỉ trả bản ổn định; muốn thấy cả rc thì lấy danh sách và chọn bản mới nhất theo semver.
            $response = $allowPre
                ? $client->get("https://api.github.com/repos/{$repo}/releases", ['per_page' => 15])
                : $client->get("https://api.github.com/repos/{$repo}/releases/latest");
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $items = $allowPre ? (array) $response->json() : [(array) $response->json()];

        $best = null;
        foreach ($items as $item) {
            $candidate = $this->normalizeRelease(is_array($item) ? $item : []);
            if ($candidate === null || (! $allowPre && $candidate['prerelease'])) {
                continue;
            }
            if ($best === null || self::isNewer($candidate['version'], $best['version'])) {
                $best = $candidate;
            }
        }

        return $best;
    }

    private function normalizeRelease(array $item): ?array
    {
        if (($item['draft'] ?? false) === true) {
            return null;
        }

        $version = ltrim((string) ($item['tag_name'] ?? ''), 'vV');
        if (self::parse($version) === null) {
            return null;
        }

        return [
            'version' => $version,
            'name' => (string) ($item['name'] ?? '') !== '' ? (string) $item['name'] : "v{$version}",
            'body' => (string) ($item['body'] ?? ''),
            'url' => (string) ($item['html_url'] ?? ''),
            'published_at' => $item['published_at'] ?? null,
            'prerelease' => self::isPrerelease($version),
        ];
    }

    /** Giây kể từ nhịp tim cuối của updater; null nếu chưa từng có. */
    public function heartbeatAge(): ?int
    {
        $raw = @file_get_contents($this->path('heartbeat'));
        if ($raw === false || ! ctype_digit(trim($raw))) {
            return null;
        }

        return max(0, time() - (int) trim($raw));
    }

    public function updaterAlive(): bool
    {
        $age = $this->heartbeatAge();

        return $age !== null && $age <= (int) config('updates.stale_after');
    }

    /**
     * Trạng thái hiển thị: idle | pending | running | success | failed.
     * "running" mà updater không còn nhịp tim = failed (stale), để admin yêu cầu lại được.
     *
     * @return array{state:string,step:?string,message:?string,from:?string,to:?string,log_tail:string,started_at:?int,updated_at:?int,stale:bool}
     */
    public function state(): array
    {
        $status = $this->readJson($this->path('status.json'));
        $state = in_array($status['state'] ?? null, ['running', 'success', 'failed'], true) ? $status['state'] : 'idle';
        $stale = false;

        if ($state === 'running' && ! $this->updaterAlive()) {
            $state = 'failed';
            $stale = true;
        }

        if ($state !== 'running' && is_file($this->requestPath())) {
            $state = 'pending';
        }

        return [
            'state' => $state,
            'step' => isset($status['step']) ? (string) $status['step'] : null,
            'message' => isset($status['message']) && $status['message'] !== '' ? (string) $status['message'] : null,
            'from' => isset($status['from']) && $status['from'] !== '' ? (string) $status['from'] : null,
            'to' => isset($status['to']) && $status['to'] !== '' ? (string) $status['to'] : null,
            'log_tail' => (string) ($status['log_tail'] ?? ''),
            'started_at' => isset($status['started_at']) ? (int) $status['started_at'] : null,
            'updated_at' => isset($status['updated_at']) ? (int) $status['updated_at'] : null,
            'stale' => $stale,
        ];
    }

    /** Lần chạy gần nhất (mới → cũ), tối đa $limit dòng từ history.jsonl do updater ghi. */
    public function history(int $limit = 5): array
    {
        $lines = @file($this->path('history.jsonl'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $rows = [];
        foreach (array_reverse($lines) as $line) {
            $row = json_decode($line, true);
            if (is_array($row) && isset($row['state'])) {
                $rows[] = $row;
            }
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /** Lý do (khoá lang updates.errors.*) không thể cập nhật lên $version, hoặc null nếu hợp lệ. */
    public function rejectionFor(string $version): ?string
    {
        if (self::parse($version) === null) {
            return 'invalid_version';
        }
        if (self::isPrerelease($version) && ! config('updates.allow_prerelease')) {
            return 'prerelease_not_allowed';
        }
        if (! self::isNewer($version, $this->currentVersion())) {
            return 'not_newer';
        }

        return null;
    }

    /**
     * Ghi request.json (tạo file tạm cùng thư mục rồi rename, updater không bao giờ đọc file dở).
     *
     * @throws UpdateRejected
     */
    public function requestUpdate(string $version, User $admin): void
    {
        if ($reason = $this->rejectionFor($version)) {
            throw UpdateRejected::because($reason, ['version' => $version]);
        }
        if (! $this->updaterAlive()) {
            throw UpdateRejected::because('updater_offline');
        }
        if (in_array($this->state()['state'], ['running', 'pending'], true)) {
            throw UpdateRejected::because('already_running');
        }

        $dir = $this->path(self::INBOX);
        if (! is_dir($dir) || ! is_writable($dir)) {
            throw UpdateRejected::because('not_writable');
        }

        $payload = json_encode(['version' => $version, 'requested_by' => $admin->id, 'at' => now()->toIso8601String()], JSON_THROW_ON_ERROR);
        $tmp = tempnam($dir, '.request-');
        if ($tmp === false) {
            throw UpdateRejected::because('not_writable');
        }

        if (@file_put_contents($tmp, $payload) === false || ! @chmod($tmp, 0644) || ! @rename($tmp, $this->requestPath())) {
            @unlink($tmp);
            throw UpdateRejected::because('not_writable');
        }
    }

    public function requestPath(): string
    {
        return $this->path(self::INBOX.'/request.json');
    }

    private function readJson(string $file): array
    {
        $raw = @file_get_contents($file);
        $data = $raw === false ? null : json_decode($raw, true);

        return is_array($data) ? $data : [];
    }
}
