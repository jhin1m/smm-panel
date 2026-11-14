# Chính sách bảo mật / Security Policy

## Phiên bản được hỗ trợ / Supported versions

Chỉ bản phát hành **ổn định mới nhất** (`latest`) nhận bản vá bảo mật. Bản `-rc.N` và các bản cũ hơn không được vá riêng: hãy cập nhật lên bản mới nhất.

Only the latest stable release receives security fixes. Release candidates and older versions are not patched separately.

## Báo cáo lỗ hổng / Reporting a vulnerability

Vui lòng **không** mở issue, discussion hoặc pull request công khai cho lỗi bảo mật.

Báo riêng tư qua GitHub Security Advisories: <https://github.com/jhin1m/smm-panel/security/advisories/new> (nút "Report a vulnerability").

Hãy kèm:
- phiên bản (tag release, hoặc nội dung file `VERSION` trong image);
- các bước tái hiện hoặc proof-of-concept;
- tác động dự kiến và điều kiện khai thác.

Please do not open public issues, discussions or pull requests for security problems. Report privately through GitHub Security Advisories (link above) with the version, reproduction steps and expected impact.

## Phản hồi / What to expect

Đây là dự án mã nguồn mở do cá nhân duy trì, không có SLA. Mục tiêu (không cam kết):
- xác nhận đã nhận báo cáo trong vòng 7 ngày;
- đánh giá và trao đổi hướng xử lý sau đó;
- phát hành bản vá cùng advisory; công bố sau khi người dùng có thời gian cập nhật, đồng thời ghi nhận người báo nếu bạn muốn.

This is a community-maintained project without an SLA. Targets (not guarantees): acknowledge within 7 days, then assess, fix and publish an advisory together with the patched release, crediting the reporter if desired.

## Phạm vi / Scope notes

Trong phạm vi: mã nguồn trong repo này, image `ghcr.io/jhin1m/smm-panel` và `ghcr.io/jhin1m/smm-panel-updater`, các script `deploy/` và `docker/`.

Các điểm sau là **thiết kế có chủ đích**, không phải lỗ hổng:
- **CSS/HTML tùy chỉnh trong Cài đặt → Giao diện** (head/body) là nội dung do admin tin cậy: admin có thể chèn script. Chỉ quản trị viên truy cập được cài đặt này, và đoạn chèn không được in cho người xem là admin.
- **Tài khoản admin bắt buộc MFA (TOTP)**. Mất thiết bị mà hết mã khôi phục thì khôi phục bằng `php artisan app:make-admin` trên máy chủ; ai có quyền shell/Docker trên VPS vốn đã toàn quyền.
- **Container `updater` giữ `docker.sock`** (tương đương root trên host): đây là điều kiện của tính năng cập nhật bằng nút. Nó không mở cổng, chỉ tải từ repo release đóng cứng trong image và kiểm checksum bundle; app chỉ gửi cho nó một chuỗi phiên bản. Quyền ghi vào thư mục cài đặt và `docker.sock` trên host nằm ngoài mô hình đe doạ.
- Nội dung thư mục `custom/` do người vận hành đặt và được tin cậy.

Ngoài phạm vi: lỗi do cấu hình sai trên VPS của bạn (firewall, lộ file `.env`), lỗi trong phần mềm bên thứ ba (hãy báo cho họ), tấn công cần quyền admin hoặc quyền truy cập máy chủ, spam/DoS thể tích lớn.

In scope: this repository's source, the two GHCR images and the `deploy/` and `docker/` scripts. By design, not vulnerabilities: admin-supplied custom CSS/head/body HTML is trusted (only admins can set it); admin MFA is mandatory; the `updater` container holds `docker.sock` by design (no exposed ports, hard-coded release source, checksum-verified bundles); `custom/` contents are operator-trusted. Out of scope: host misconfiguration, third-party software bugs, attacks that already require admin or server access.
