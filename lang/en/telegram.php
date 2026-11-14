<?php

// Messages sent to admins via Telegram: always translated in the site default locale, never the actor's.
return [
    'test_message' => '<b>:app</b>: test message from the settings page.',
    'bank_account_changed' => "⚠️ The receiving bank account was just changed by :email:\n:lines",
    'sepay_no_secret' => 'The SePay webhook is returning 503 because SEPAY_WEBHOOK_SECRET is missing. Set the secret under Settings → Integrations (or .env), then run reconciliation.',
    'sepay_bad_signature' => 'SePay webhook: :count requests with an invalid signature in 10 minutes (latest IP :ip).',
    'deposit_suspected_duplicate' => 'SePay transaction #:id looks like a duplicate of #:twin (reference code :reference, :amountđ) — not credited, needs a manual check.',
    'deposit_manual_import' => 'Manual entry of SePay transaction #:id (:amountđ) by :email: :result',
    'reconcile_failed' => 'SePay reconciliation failed: :error — deposits missed by the webhook will not be recovered until this is fixed.',
    'reconcile_row_errors' => 'SePay reconciliation: :count rows failed — see the log.',
    'chat_new_message' => "<b>New message</b> from :name (:email)\n:body\n:url",
];
