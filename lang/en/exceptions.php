<?php

return [
    'confirmation_required' => 'This transaction was marked as skipped — confirmation is required before assigning it.',
    'deposit_already_processed' => 'The transaction has already been processed or is not in a valid state.',
    'idempotency_conflict' => 'The idempotency key was already used for a different ledger entry: :key',
    'insufficient_balance' => 'Insufficient balance: :balance available, :required required',
    'invalid_order_transition' => 'Cannot move the order from ":from" to ":to"',
    'self_adjustment_forbidden' => 'You cannot adjust your own balance',
    'stale_order_state' => 'The order was just updated by someone else, please reload.',
    'invalid_tx_amount' => 'Amount :amount is not valid for type :type',
];
