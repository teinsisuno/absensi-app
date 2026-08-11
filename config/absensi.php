<?php

return [
    /*
    | Secret bersama dengan Central untuk memverifikasi signed token SSO.
    | Wajib SAMA dengan yang dipakai Central saat generate token.
    */
    'sso_secret' => env('ABSENSI_SSO_SECRET', ''),

    /*
    | Secret untuk webhook provisioning dari Central (header X-Absensi-Webhook-Secret).
    */
    'webhook_secret' => env('ABSENSI_WEBHOOK_SECRET', ''),

    /*
    | Pola subdomain tenant: {slug}-absensi.megakomsel.com
    */
    'tenant_domain_pattern' => env('ABSENSI_TENANT_DOMAIN_PATTERN', '{slug}-absensi.megakomsel.com'),
];
