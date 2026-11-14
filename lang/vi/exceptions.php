<?php

return [
    'confirmation_required' => 'Giao dịch này đã bị đánh dấu bỏ qua — cần xác nhận trước khi gán.',
    'deposit_already_processed' => 'Giao dịch đã được xử lý hoặc không ở trạng thái phù hợp.',
    'idempotency_conflict' => 'Idempotency key đã dùng cho bút toán khác: :key',
    'insufficient_balance' => 'Số dư không đủ: còn :balance, cần :required',
    'invalid_order_transition' => 'Không thể chuyển đơn từ ":from" sang ":to"',
    'self_adjustment_forbidden' => 'Không được điều chỉnh số dư của chính mình',
    'stale_order_state' => 'Đơn vừa được người khác cập nhật, hãy tải lại.',
    'invalid_tx_amount' => 'Số tiền :amount không hợp lệ cho loại :type',
];
