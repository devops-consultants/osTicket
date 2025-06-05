#!/usr/bin/env php
<?php
/**
 * Advanced test script for the osTicket Thread API
 * This script creates a new thread entry with canned responses and attachments
 * 
 * Usage: php test-thread-api-advanced.php [ticketId] [apiKey]
 */

// Configuration
$apiUrl = 'http://localhost/api/tickets/';
$apiKey = isset($argv[2]) ? $argv[2] : 'YOUR_API_KEY'; 
$ticketId = isset($argv[1]) ? $argv[1] : '1';
$cannedResponseId = 1; // ID of the canned response to use

// Select a test mode
$testMode = isset($argv[3]) ? $argv[3] : 'reply';

// Different test scenarios
switch ($testMode) {
    case 'note':
        // Test case: Add an internal note
        $data = array(
            'thread_type' => 'note',
            'message' => 'This is an internal note created via API at ' . date('Y-m-d H:i:s'),
            'alert' => false,
            'poster' => 'API Test Script',
            'staffId' => 1,
        );
        break;
        
    case 'reply':
        // Test case: Add a reply
        $data = array(
            'thread_type' => 'response',
            'message' => 'This is a public reply created via API at ' . date('Y-m-d H:i:s'),
            'alert' => true,
            'poster' => 'API Test Script',
            'staffId' => 1,
            'includeSignature' => true,
            'signature' => 'mine',
        );
        break;
        
    case 'canned':
        // Test case: Use a canned response
        $data = array(
            'thread_type' => 'response',
            'cannedId' => $cannedResponseId,
            'cannedMode' => 'append',
            'message' => 'Additional information: This was appended to a canned response.',
            'cannedAttachments' => true,
            'alert' => true,
            'staffId' => 1,
        );
        break;
        
    case 'status':
        // Test case: Change ticket status with reply
        $data = array(
            'thread_type' => 'response',
            'message' => 'This issue has been resolved. Closing the ticket.',
            'status_id' => 3, // Assuming 3 is the ID for "Closed" status
            'alert' => true,
            'staffId' => 1,
        );
        break;
        
    case 'attachment':
        // Test case: Add a reply with attachment
        $testFile = __DIR__ . '/test-attachment.txt';
        
        // Create a test file if it doesn't exist
        if (!file_exists($testFile)) {
            file_put_contents($testFile, "This is a test attachment file.\nCreated at: " . date('Y-m-d H:i:s'));
        }
        
        $data = array(
            'thread_type' => 'response',
            'message' => 'Please find the requested information in the attachment.',
            'alert' => true,
            'staffId' => 1,
            'attachments' => array(
                array(
                    'name' => 'test-attachment.txt',
                    'type' => 'text/plain',
                    'data' => base64_encode(file_get_contents($testFile)),
                    'encoding' => 'base64'
                )
            )
        );
        break;
        
    default:
        echo "Unknown test mode: $testMode\n";
        echo "Available modes: note, reply, canned, status, attachment\n";
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
echo "Testing mode: $testMode\n";
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
