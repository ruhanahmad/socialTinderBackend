<?php
/**
 * Simple API Test Script
 * This script demonstrates how to test the Laravel API endpoints
 * Run this after setting up your Laravel project with PHP and Composer
 */

// Base URL for the API
$baseUrl = 'http://localhost:8000/api';

// Test data
$testUser = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'password123',
    'country' => 'United States',
    'phone_number' => '+1234567890'
];

echo "=== Laravel API Test Script ===\n\n";

// Function to make HTTP requests
function makeRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

// Test 1: Register User
echo "1. Testing User Registration...\n";
$registerResult = makeRequest($baseUrl . '/register', 'POST', $testUser);

if ($registerResult['code'] === 201) {
    echo "✅ Registration successful!\n";
    $token = $registerResult['response']['data']['token'];
    echo "Token: " . substr($token, 0, 20) . "...\n\n";
} else {
    echo "❌ Registration failed: " . $registerResult['response']['message'] . "\n\n";
}

// Test 2: Login User
echo "2. Testing User Login...\n";
$loginData = [
    'email' => $testUser['email'],
    'password' => $testUser['password']
];

$loginResult = makeRequest($baseUrl . '/login', 'POST', $loginData);

if ($loginResult['code'] === 200) {
    echo "✅ Login successful!\n";
    $token = $loginResult['response']['data']['token'];
    echo "Token: " . substr($token, 0, 20) . "...\n\n";
} else {
    echo "❌ Login failed: " . $loginResult['response']['message'] . "\n\n";
}

// Test 3: Get User Profile
echo "3. Testing Get Profile...\n";
$profileResult = makeRequest($baseUrl . '/profile', 'GET', null, $token);

if ($profileResult['code'] === 200) {
    echo "✅ Profile retrieved successfully!\n";
    $user = $profileResult['response']['data']['user'];
    echo "User: {$user['name']} ({$user['email']})\n\n";
} else {
    echo "❌ Profile retrieval failed: " . $profileResult['response']['message'] . "\n\n";
}

// Test 4: Logout
echo "4. Testing Logout...\n";
$logoutResult = makeRequest($baseUrl . '/logout', 'POST', null, $token);

if ($logoutResult['code'] === 200) {
    echo "✅ Logout successful!\n\n";
} else {
    echo "❌ Logout failed: " . $logoutResult['response']['message'] . "\n\n";
}

// Test 5: Get Users by Country
echo "5. Testing Get Users by Country...\n";
$usersResult = makeRequest($baseUrl . '/users', 'GET', null, $token);

if ($usersResult['code'] === 200) {
    echo "✅ Users retrieved successfully!\n";
    $users = $usersResult['response']['data']['users'];
    echo "Found " . count($users) . " users in the same country\n\n";
} else {
    echo "❌ Users retrieval failed: " . $usersResult['response']['message'] . "\n\n";
}

// Test 6: Update Profile
echo "6. Testing Profile Update...\n";
$profileData = [
    'description' => 'This is my updated profile description!'
];

$updateResult = makeRequest($baseUrl . '/profile', 'PUT', $profileData, $token);

if ($updateResult['code'] === 200) {
    echo "✅ Profile updated successfully!\n";
    echo "Description: " . $updateResult['response']['data']['user']['description'] . "\n\n";
} else {
    echo "❌ Profile update failed: " . $updateResult['response']['message'] . "\n\n";
}

// Test 7: Like a User (if there are other users)
if (!empty($users)) {
    $firstUserId = $users[0]['id'];
    echo "7. Testing Like User...\n";
    $likeData = ['action' => 'like'];
    $likeResult = makeRequest($baseUrl . "/users/{$firstUserId}/like", 'POST', $likeData, $token);

    if ($likeResult['code'] === 200) {
        echo "✅ User liked successfully!\n\n";
    } else {
        echo "❌ Like failed: " . $likeResult['response']['message'] . "\n\n";
    }
}

// Test 8: Get My Likes
echo "8. Testing Get My Likes...\n";
$myLikesResult = makeRequest($baseUrl . '/my-likes', 'GET', null, $token);

if ($myLikesResult['code'] === 200) {
    echo "✅ Likes retrieved successfully!\n";
    $myLikes = $myLikesResult['response']['data']['my_likes'];
    echo "My likes count: " . count($myLikes) . "\n\n";
} else {
    echo "❌ Likes retrieval failed: " . $myLikesResult['response']['message'] . "\n\n";
}

// Test 9: Get Countries
echo "9. Testing Get Countries...\n";
$countriesResult = makeRequest($baseUrl . '/countries', 'GET', null, $token);

if ($countriesResult['code'] === 200) {
    echo "✅ Countries retrieved successfully!\n";
    $countries = $countriesResult['response']['data']['countries'];
    echo "Available countries: " . implode(', ', $countries) . "\n\n";
} else {
    echo "❌ Countries retrieval failed: " . $countriesResult['response']['message'] . "\n\n";
}

echo "=== Test Complete ===\n";
echo "To run this test:\n";
echo "1. Install PHP and Composer\n";
echo "2. Run: composer install\n";
echo "3. Copy .env.example to .env and configure database\n";
echo "4. Run: php artisan migrate\n";
echo "5. Run: php artisan storage:link\n";
echo "6. Run: php artisan serve\n";
echo "7. Run: php test_api.php\n"; 