# Quy trình phát hành

Repo công khai: `jhin1m/smm-panel`. Image: `ghcr.io/jhin1m/smm-panel`. Giấy phép MIT.

## Phiên bản (semver)

- `vX.Y.Z` ổn định; `vX.Y.Z-rc.N` thử nghiệm (prerelease). Không dùng hậu tố khác: workflow từ chối.
- MAJOR: thay đổi phá vỡ (đổi cấu trúc `.env`/compose phải sửa tay, bỏ view/biến mà `custom/views` đang dùng). MINOR: tính năng mới tương thích ngược. PATCH: sửa lỗi.
- Tag git có tiền tố `v` (`v1.2.3`); tên asset Release và `APP_VERSION` trong `.env` dùng đúng tag này. Tag image GHCR, file `VERSION` trong image và khoá `version` trong `release.json` không có `v` (`1.2.3`).
- `:latest` chỉ trỏ tới bản ổn định mới nhất. Bản rc không bao giờ đổi `:latest` và không được `install.sh` chọn mặc định.

## Quy ước tương thích (bắt buộc)

- **Migration chỉ thêm**: thêm bảng/cột (cột mới nullable hoặc có default). Không đổi tên/xoá cột trong cùng bản phát hành với code dùng nó. Cập nhật có thể quay về bản cũ nên schema mới phải chịu được code cũ.
- **Property settings mới phải có giá trị mặc định PHP** (và migration settings dùng `add`), để instance cũ chưa có dòng trong bảng `settings` vẫn đọc được.
- Đổi view lõi có thể làm hỏng `custom/views` của người dùng: ghi rõ trong release notes.

## Checklist trước khi cắt tag

1. `main` xanh CI (test, lint, secrets).
2. `docker compose -f compose.dev.yml run --rm test` xanh cục bộ; `vendor/bin/pint --test` sạch.
3. Migration/settings mới tuân thủ quy ước trên (kiểm trên bản sao DB của instance thật nếu có).
4. Đã soạn sẵn nội dung hai mục "View thay đổi" và "Migration" (xem dưới).
5. Với bản ổn định: đã có rc tương ứng, đã cài thử `install.sh --version <rc>` trên VM sạch và cập nhật từ bản trước lên.

## Cắt tag

```bash
git checkout main && git pull
git tag -a v1.2.3-rc.1 -m "v1.2.3-rc.1"     # hoặc v1.2.3 cho bản ổn định
git push origin v1.2.3-rc.1
```

Workflow `Release` (`.github/workflows/release.yml`) chạy tự động: CI → dựng image `--target runtime` (truyền `APP_VERSION`) → push `ghcr.io/jhin1m/smm-panel:X.Y.Z` (+ `:latest` nếu không phải rc) → kiểm image pull được khi chưa đăng nhập → dựng bundle → tạo Release.

Asset đính kèm Release (dựng bằng `deploy/build-bundle.sh`, cùng script mà bài kiểm tra `tests/shell/bundle-install-contract.sh` dùng để đối chiếu với `install.sh`):

| Asset | Nội dung |
|---|---|
| `smm-panel-deploy-vX.Y.Z.tar.gz` | `compose.yml`, `.env.template`, `docker/caddy/Caddyfile`, `docker/backup/backup-db.sh`, `custom/README.md` (+ `update.sh` khi có) |
| `release.json` | `{"version": "X.Y.Z", "app_image": "ghcr.io/jhin1m/smm-panel@sha256:..."}` |
| `checksums.txt` | `sha256sum` của tarball, `release.json`, `install.sh` |
| `install.sh` | bản cùng commit với tag |

### Lần đầu tiên: package GHCR

Package mới tạo mặc định là **private**. Lần đầu (rc.1) bước "Kiểm image pull được khi không đăng nhập" sẽ dừng workflow và nhắc việc: vào GitHub → Packages → `smm-panel` → Package settings → Change visibility → Public, rồi **Re-run failed jobs**. Chỉ cần làm một lần.

### Release notes

Release được tạo kèm notes tự sinh và hai mục có sẵn tiêu đề. **Sửa Release và điền hai mục này trước khi thông báo** (ghi "Không có" nếu không đổi):

- **View thay đổi**: view/component/biến nào của lõi đổi, để người có `custom/views` đối chiếu.
- **Migration**: migration/settings mới, có tác động dữ liệu hay thời gian chạy lâu không; xác nhận chỉ thêm và có default.

## Sau khi phát hành

1. Release có đủ 4 asset; `release.json` có `app_image` ghim digest; bản rc không làm đổi `:latest`.
2. `docker pull ghcr.io/jhin1m/smm-panel:X.Y.Z` chạy được trên máy chưa đăng nhập.
3. Bản ổn định: `curl -fsSL https://api.github.com/repos/jhin1m/smm-panel/releases/latest` trả đúng tag.

## Tạo repo công khai từ snapshot sạch (làm một lần)

Mục đích: repo public không mang lịch sử, `plans/`, nhật ký, mockup hay `.claude/`. `.gitattributes` đã `export-ignore` các thư mục đó nên `git archive` tự bỏ.

```bash
# 1. Từ repo phát triển, ở commit muốn công khai (đã sạch, đã commit)
SNAP=~/smm-panel-public
mkdir "$SNAP"
git -C <repo-phat-trien> archive --format=tar HEAD | tar -x -C "$SNAP"

# 2. Kiểm snapshot không chứa thứ không được công khai
cd "$SNAP"
ls -a                      # không có plans/, .claude/, docs/journals, docs/mockup, .env
gitleaks detect --no-git --source . --redact    # phải sạch

# 3. Khởi tạo repo mới và commit đầu
git init -b main
git add -A
git commit -m "chore: initial public snapshot"
gitleaks detect --source . --redact             # quét lại trên commit

# 4. Tạo repo public trên GitHub (trống, không README/LICENSE tự sinh) rồi đẩy lên
gh repo create jhin1m/smm-panel --public --source . --remote origin --push
```

Sau đó: bật Private vulnerability reporting (Settings → Code security) để `SECURITY.md` có hiệu lực, rồi cắt `v0.1.0-rc.1` theo mục trên. Phát triển tiếp trên repo công khai; `plans/` và `docs/journals/` giữ ở repo riêng hoặc cục bộ (thêm vào `.git/info/exclude` nếu giữ cùng thư mục làm việc).
