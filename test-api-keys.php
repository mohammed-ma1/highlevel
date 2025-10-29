<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== API Keys Test ===\n";

// Check if there are any users with API keys
$usersWithApiKeys = User::whereNotNull('lead_test_api_key')->get();

echo "Users with lead_test_api_key: " . $usersWithApiKeys->count() . "\n";

if ($usersWithApiKeys->count() > 0) {
    $user = $usersWithApiKeys->first();
    echo "First user ID: " . $user->id . "\n";
    echo "API Key prefix: " . substr($user->lead_test_api_key, 0, 10) . "...\n";
    echo "Publishable Key prefix: " . substr($user->lead_test_publishable_key, 0, 10) . "...\n";
} else {
    echo "No users found with API keys!\n";
    
    // Check all users
    $allUsers = User::all();
    echo "Total users in database: " . $allUsers->count() . "\n";
    
    if ($allUsers->count() > 0) {
        $user = $allUsers->first();
        echo "First user columns:\n";
        foreach ($user->getAttributes() as $key => $value) {
            if (strpos($key, 'api') !== false || strpos($key, 'key') !== false) {
                echo "  $key: " . (is_null($value) ? 'NULL' : 'HAS_VALUE') . "\n";
            }
        }
    }
}

echo "\n=== Test Complete ===\n";



