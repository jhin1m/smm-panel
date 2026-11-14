<?php

// Tin gửi admin qua Telegram: luôn dịch theo ngôn ngữ mặc định của site, không theo người kích hoạt.
return [
    'test_message' => '<b>:app</b>: tin nhắn thử từ trang cài đặt.',
    'bank_account_changed' => "⚠️ Tài khoản nhận tiền vừa đổi bởi :email:\n:lines",
    'sepay_no_secret' => 'Webhook SePay đang trả 503 vì thiếu SEPAY_WEBHOOK_SECRET. Đặt secret ở Cài đặt → Tích hợp (hoặc .env) rồi chạy đối soát.',
    'sepay_bad_signature' => 'Webhook SePay: :count request sai chữ ký trong 10 phút (IP gần nhất :ip).',
    'deposit_suspected_duplicate' => 'Giao dịch SePay #:id nghi trùng #:twin (mã tham chiếu :reference, :amountđ) — chưa cộng, cần kiểm tay.',
    'deposit_manual_import' => 'Nhập tay giao dịch SePay #:id (:amountđ) bởi :email: :result',
    'reconcile_failed' => 'Đối soát SePay thất bại: :error — tiền nạp bị lỡ webhook sẽ không được bù cho tới khi sửa.',
    'reconcile_row_errors' => 'Đối soát SePay: :count dòng lỗi — xem log.',
    'chat_new_message' => "<b>Tin nhắn mới</b> từ :name (:email)\n:body\n:url",
];
