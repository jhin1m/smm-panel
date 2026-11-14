<?php

return [
    'title' => 'Account security',
    'intro' => 'Turn on two-factor authentication (2FA) so only you can sign in, even if your password leaks.',
    'status' => [
        'off' => 'Off',
        'pending' => 'Setting up',
        'enabled' => 'On',
    ],
    'feature_off' => 'The administrator has temporarily disabled turning on new 2FA.',
    'enable' => 'Turn on 2FA',
    'regenerate_qr' => 'Regenerate QR code',
    'cancel_setup' => 'Cancel setup',
    'setup' => [
        'scan' => 'Scan the QR code with Google Authenticator (or another TOTP app), then enter the 6-digit code to confirm.',
        'qr_alt' => '2FA setup QR code',
        'secret' => 'Cannot scan? Enter this key in the app:',
        'code' => '6-digit code',
        'confirm' => 'Confirm and turn on',
    ],
    'recovery' => [
        'title' => 'Recovery codes',
        'hint' => 'Store these codes somewhere safe. Each one works once if you lose your phone. They are shown only now.',
        'saved' => 'I have saved them',
    ],
    'manage' => [
        'title' => 'Manage 2FA',
        'hint' => 'Enter your current 6-digit code or a recovery code to continue.',
        'code' => '6-digit code or recovery code',
        'regenerate' => 'Regenerate recovery codes',
        'disable' => 'Turn off 2FA',
    ],
    'challenge' => [
        'title' => 'Two-factor authentication',
        'intro' => 'Enter the 6-digit code from your authenticator app.',
        'code' => '6-digit code',
        'recovery_intro' => 'Lost your phone? Enter a recovery code (each works once).',
        'recovery_code' => 'Recovery code',
        'use_recovery' => 'Use a recovery code',
        'submit' => 'Confirm',
        'back' => 'Back to sign in',
    ],
    'errors' => [
        'invalid_code' => 'The authentication code is incorrect.',
        'invalid_recovery_code' => 'The recovery code is incorrect.',
        'code_required' => 'Please enter a code.',
        'too_many' => 'Too many wrong attempts. Try again in :seconds seconds.',
    ],
    'status_messages' => [
        'enabled' => '2FA is on. Your other sessions were signed out.',
        'disabled' => '2FA is off. Your other sessions were signed out.',
        'regenerated' => 'New recovery codes generated; the old ones no longer work.',
    ],
];
