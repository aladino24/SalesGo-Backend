<?php

return [
    'location_retention_days' => (int) env('SALES_LOCATION_RETENTION_DAYS', 90),
    'report_aggregate_lookback_days' => (int) env('REPORT_AGGREGATE_LOOKBACK_DAYS', 7),
    'report_archive_retention_days' => (int) env('REPORT_ARCHIVE_RETENTION_DAYS', 2555),
    'fcm_enabled' => (bool) env('FCM_ENABLED', false),
    'firebase_project_id' => env('FIREBASE_PROJECT_ID'),
    'order' => [
        // Dapat diubah per environment tanpa merilis ulang aplikasi mobile.
        'minimum_units' => (int) env('ORDER_MINIMUM_UNITS', 1),
        'maximum_units' => (int) env('ORDER_MAXIMUM_UNITS', 1000),
        'minimum_amount' => (float) env('ORDER_MINIMUM_AMOUNT', 0),
        // Nilai 0 berarti tidak ada batas nominal maksimum.
        'maximum_amount' => (float) env('ORDER_MAXIMUM_AMOUNT', 0),
    ],
    'payment' => [
        'require_check_in' => (bool) env('PAYMENT_REQUIRE_CHECK_IN', true),
    ],
];
