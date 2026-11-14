<?php

return [
    'title' => 'Add funds',
    'intro' => 'Transfer with the exact transfer content. The system reconciles and credits your balance automatically — no receipt needed.',
    'balance' => 'Balance :amount',
    'not_open' => 'Top-ups are not open yet. Please contact an admin via chat.',
    'amount' => 'Amount',
    'custom_amount' => 'Or enter another amount',
    'min_hint' => 'Minimum amount is :amount. You can still scan the QR and enter the amount yourself.',
    'qr_alt' => 'Transfer QR code :code',
    'bank' => 'Bank',
    'account_number' => 'Account no.',
    'account_name' => 'Account holder',
    'any_amount' => 'Any',
    'content' => 'Transfer content',
    'bonus' => 'Bonus',
    'waiting' => 'Waiting for your transfer…',
    'note' => 'Use exactly :code as the transfer content (your fixed deposit code). Funds are usually credited within 5–30 seconds.',
    'history' => 'Deposit history',
    'time' => 'Time',
    'type' => 'Type',
    'empty' => 'No deposits yet.',
    'received' => 'Received :amount. New balance :balance',
    // System notes are stored in bank_transactions.note as keys; displayed through BankTransaction::noteLabel().
    'notes' => [
        'transfer_out' => 'Outgoing transfer',
        'wrong_account' => 'Wrong receiving account number',
        'suspected_duplicate' => 'Suspected duplicate of SePay transaction #:twin (same reference code)',
        'unassigned' => 'Unassigned: :reason',
    ],
];
