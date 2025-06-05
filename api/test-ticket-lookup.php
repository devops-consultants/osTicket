#!/usr/bin/env php
<?php
/**
 * Ticket Lookup Test Script
 * Test script for looking up tickets by ticket number
 * 
 * Usage: php test-ticket-lookup.php [ticketNumber] [apiKey]
 */

// Configuration
$apiUrl = isset($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'].'/api/tickets/' : 'http://localhost/api/tickets/';
$apiKey = isset($argv[2]) ? $argv[2] : 'YOUR_API_KEY'; 
$ticketNumber = isset($argv[1]) ? $argv[1] : null;

// Check if ticket number is provided
if (!$ticketNumber) {
    echo "Error: Ticket number is required.\n";
    echo "Usage: php test-ticket-lookup.php [ticketNumber] [apiKey]\n";
    exit(1);
}

// API endpoint URL
$url = $apiUrl . "number/{$ticketNumber}.json";

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'X-API-Key: ' . $apiKey,
    'Content-Type: application/json',
    'Accept: application/json',
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Execute the request
echo "Looking up ticket number: {$ticketNumber}\n";
echo "Sending request to: {$url}\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if (curl_errno($ch)) {
    echo "Error: " . curl_error($ch) . "\n";
} else {
    echo "HTTP Response Code: {$httpCode}\n";
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
