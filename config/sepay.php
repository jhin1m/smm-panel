<?php

return [
    // HMAC-SHA256: header X-SePay-Signature: sha256=<hex> của "{timestamp}.{raw_body}". Trống → webhook trả 503 (fail closed).
    'webhook_secret' => env('SEPAY_WEBHOOK_SECRET'),

    // Token User API (legacy userapi, id giao dịch dạng số = id trong webhook) dùng cho đối soát.
    'api_token' => env('SEPAY_API_TOKEN'),
    'api_url' => env('SEPAY_API_URL', 'https://my.sepay.vn/userapi/transactions/list'),

    // Lệch tối đa giữa X-SePay-Timestamp và giờ server (giây). Webhook trễ hơn sẽ được đối soát bù.
    'timestamp_tolerance' => 300,

    // IP SePay (https://developer.sepay.vn/en/dia-chi-ip) — CHỈ để log cảnh báo, không chặn.
    'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('SEPAY_ALLOWED_IPS', ''))))) ?: [
        '172.236.138.20', '172.233.83.68', '171.244.35.2', '151.158.108.68', '151.158.109.79',
        '103.255.238.139', '45.57.137.67', '2400:8905::2000:8cff:fe98:45cd', '2600:3c15::2000:8aff:fedd:874b',
    ],
];
