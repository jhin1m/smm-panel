<?php

return [
    'title' => 'Bảo mật tài khoản',
    'intro' => 'Bật xác thực hai lớp (2FA) để chỉ bạn mới đăng nhập được, kể cả khi lộ mật khẩu.',
    'status' => [
        'off' => 'Chưa bật',
        'pending' => 'Đang thiết lập',
        'enabled' => 'Đã bật',
    ],
    'feature_off' => 'Quản trị viên đang tạm tắt việc bật 2FA mới.',
    'enable' => 'Bật 2FA',
    'regenerate_qr' => 'Tạo lại mã QR',
    'cancel_setup' => 'Huỷ thiết lập',
    'setup' => [
        'scan' => 'Quét mã QR bằng Google Authenticator (hoặc ứng dụng TOTP khác), rồi nhập mã 6 số để xác nhận.',
        'qr_alt' => 'Mã QR thiết lập 2FA',
        'secret' => 'Không quét được? Nhập khoá này vào ứng dụng:',
        'code' => 'Mã 6 số',
        'confirm' => 'Xác nhận và bật',
    ],
    'recovery' => [
        'title' => 'Mã khôi phục',
        'hint' => 'Lưu các mã này ở nơi an toàn. Mỗi mã dùng được một lần khi mất điện thoại. Mã chỉ hiện lúc này.',
        'saved' => 'Tôi đã lưu',
    ],
    'manage' => [
        'title' => 'Quản lý 2FA',
        'hint' => 'Nhập mã 6 số hiện tại hoặc một mã khôi phục để tiếp tục.',
        'code' => 'Mã 6 số hoặc mã khôi phục',
        'regenerate' => 'Tạo lại mã khôi phục',
        'disable' => 'Tắt 2FA',
    ],
    'challenge' => [
        'title' => 'Xác thực hai lớp',
        'intro' => 'Nhập mã 6 số từ ứng dụng xác thực của bạn.',
        'code' => 'Mã 6 số',
        'recovery_intro' => 'Mất điện thoại? Nhập một mã khôi phục (mỗi mã chỉ dùng một lần).',
        'recovery_code' => 'Mã khôi phục',
        'use_recovery' => 'Dùng mã khôi phục',
        'submit' => 'Xác nhận',
        'back' => 'Quay lại đăng nhập',
    ],
    'errors' => [
        'invalid_code' => 'Mã xác thực không đúng.',
        'invalid_recovery_code' => 'Mã khôi phục không đúng.',
        'code_required' => 'Vui lòng nhập mã.',
        'too_many' => 'Nhập sai quá nhiều lần. Thử lại sau :seconds giây.',
    ],
    'status_messages' => [
        'enabled' => 'Đã bật 2FA. Các phiên đăng nhập khác đã bị đăng xuất.',
        'disabled' => 'Đã tắt 2FA. Các phiên đăng nhập khác đã bị đăng xuất.',
        'regenerated' => 'Đã tạo mã khôi phục mới, mã cũ hết hiệu lực.',
    ],
];
