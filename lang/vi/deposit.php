<?php

return [
    'title' => 'Nạp tiền',
    'intro' => 'Chuyển khoản đúng nội dung, hệ thống tự đối soát và cộng tiền, không cần gửi ảnh biên lai.',
    'balance' => 'Số dư :amount',
    'not_open' => 'Chưa mở nạp tiền. Vui lòng liên hệ quản trị viên qua chat.',
    'amount' => 'Số tiền',
    'custom_amount' => 'Hoặc nhập số khác',
    'min_hint' => 'Số tiền tối thiểu :amount. Bạn vẫn có thể quét QR và tự nhập số tiền.',
    'qr_alt' => 'Mã QR chuyển khoản :code',
    'bank' => 'Ngân hàng',
    'account_number' => 'Số TK',
    'account_name' => 'Chủ TK',
    'any_amount' => 'Tuỳ chọn',
    'content' => 'Nội dung',
    'bonus' => 'Khuyến mãi',
    'waiting' => 'Đang chờ chuyển khoản…',
    'note' => 'Ghi đúng nội dung :code (mã nạp cố định của bạn). Tiền thường được cộng sau 5–30 giây.',
    'history' => 'Lịch sử nạp',
    'time' => 'Thời gian',
    'type' => 'Loại',
    'empty' => 'Chưa có giao dịch nạp.',
    'received' => 'Đã nhận :amount. Số dư mới :balance',
    // Ghi chú hệ thống lưu vào bank_transactions.note dưới dạng key; hiển thị qua BankTransaction::noteLabel().
    'notes' => [
        'transfer_out' => 'Tiền ra (transfer out)',
        'wrong_account' => 'Sai số tài khoản nhận',
        'suspected_duplicate' => 'Nghi trùng giao dịch SePay #:twin (cùng mã tham chiếu)',
        'unassigned' => 'Huỷ gán: :reason',
    ],
];
