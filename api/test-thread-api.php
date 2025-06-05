#!/usr/bin/env php
<?php
/**
 * Test script for the osTicket Thread API
 * This script creates a new thread entry (note or reply) on an existing ticket
 * 
 * Usage: php test-thread-api.php [ticketId] [apiKey]
 */

// Configuration
$apiUrl = 'http://localhost/api/tickets/';
$apiKey = isset($argv[2]) ? $argv[2] : 'YOUR_API_KEY'; 
$ticketId = isset($argv[1]) ? $argv[1] : '1';

// Data to send
$data = array(
    'thread_type' => 'note', // 'note' or 'response'
    'message' => 'This is a test message via API at ' . date('Y-m-d H:i:s'),
    'alert' => true,
    'poster' => 'API Test Script',
);

// Create the JSON request
$json = json_encode($data);

// API endpoint URL
$url = $apiUrl . $ticketId . '/add_thread.json';

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'X-API-Key: ' . $apiKey,
    'Content-Type: application/json',
    'Accept: application/json',
));
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Execute the request
echo "Sending request to: $url\n";
echo "Request data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if (curl_errno($ch)) {
    echo "Error: " . curl_error($ch) . "\n";
} else {
    echo "HTTP Response Code: $httpCode\n";
    echo "Response: \n";
    
    // Pretty print the JSON response
    if ($response) {
        $jsonResponse = json_decode($response, true);
        if ($jsonResponse) {
            echo json_encode($jsonResponse, JSON_PRETTY_PRINT);
        } else {
            echo $response;
        }
    } else {
        echo "Empty response\n";
    }
}

curl_close($ch);
echo "\n";
