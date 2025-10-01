<?php
// api/purchase_behavior.php
require __DIR__ . '/_bootstrap.php';
$uid = require_login();

try {
    // Replace with your real SQL queries to fetch customer purchase insights
    // Example placeholders for demo
    $categories = [
        'Average Purchase Value', 
        'Basket Size', 
        'Frequent Products', 
        'Purchase Frequency'
    ];
    $values = [
        1585,  // Average Purchase Value ₱
        5,     // Items per basket
        3,     // Frequent products purchased
        8      // Purchase frequency per month
    ];

    json_ok(['categories' => $categories, 'values' => $values]);
} catch (Throwable $e) {
    json_error('Purchase behavior error', 500, ['detail' => $e->getMessage()]);
}
