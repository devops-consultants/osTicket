#!/usr/bin/env php
<?php
/**
 * Client Reply API Test Script
 * Test script for simulating client/user email replies to tickets via the API
 * 
 * Usage: php test-client-reply-api.php [ticketId] [apiKey] [mode]
 * Available modes: 
 *   basic - Simple text reply
 *   attachment - Reply with attachment
 *   collaborator - Reply as a collaborator
 */

// Configuration
$apiUrl = isset($_SERVER['HTTP_HOST']) ? 'http://'.$_SERVER['HTTP_HOST'].'/api/tickets/' : 'http://localhost/api/tickets/';
$apiKey = isset($argv[2]) ? $argv[2] : 'YOUR_API_KEY'; 
$ticketId = isset($argv[1]) ? $argv[1] : '1';
$testMode = isset($argv[3]) ? $argv[3] : 'basic';

// Different test scenarios
switch ($testMode) {
    case 'basic':
        // Test case: Basic client reply
        $data = array(
            'as_client' => true,
            'message' => "This is a client reply via API at " . date('Y-m-d H:i:s') . "\n\nThank you for your assistance.",
        );
        break;
        
    case 'attachment':
        // Test case: Client reply with attachment
        $testFile = __DIR__ . '/test-attachment.txt';
        
        // Create a test file if it doesn't exist
        if (!file_exists($testFile)) {
            file_put_contents($testFile, "This is a test attachment file from client.\nCreated at: " . date('Y-m-d H:i:s'));
        }
        
        $data = array(
            'as_client' => true,
            'message' => "Please find the requested information in the attachment.\nSent via API at " . date('Y-m-d H:i:s'),
            'attachments' => array(
                array(
                    'name' => 'client-attachment.txt',
                    'type' => 'text/plain',
                    'data' => base64_encode(file_get_contents($testFile)),
                    'encoding' => 'base64'
                )
            )
        );
        break;
        
    case 'collaborator':
        // Test case: Reply as a collaborator (assumes collaborator ID = 2)
        $data = array(
            'as_client' => true,
            'userId' => 2, // Set this to a valid collaborator ID
            'message' => "This is a reply from a collaborator via API.\nSent at: " . date('Y-m-d H:i:s'),
        );
        break;
        
    default:
        echo "Unknown test mode: $testMode\n";
        echo "Available modes: basic, attachment, collaborator\n";
        exit(1);
}

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
echo "Testing client reply mode: $testMode\n";
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
