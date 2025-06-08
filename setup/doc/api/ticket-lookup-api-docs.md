# osTicket Ticket Lookup API Documentation

## Overview

This API endpoint enables applications to look up ticket details by ticket number instead of the internal ticket ID. This is useful for integrating with external systems that may only have the ticket number (which is displayed to clients) and not the internal ID.

## Endpoint

```
GET /api/tickets/number/{ticketNumber}.json
GET /api/tickets/number/{ticketNumber}.xml
```

## Authentication

Authentication is done via API key using the `X-API-Key` header.

## Parameters

| Parameter     | Description                                                 |
|---------------|-------------------------------------------------------------|
| ticketNumber  | The ticket number to look up (case-sensitive)               |

## Response Format

The response includes comprehensive ticket information:

```json
{
  "id": 123,
  "number": "ABC123",
  "subject": "Issue with product",
  "status": "Open",
  "priority": "Normal",
  "department": "Support",
  "created": "2025-05-24 12:34:56",
  "updated": "2025-05-24 14:22:33",
  "assigned_to": {
    "id": 5,
    "name": "John Support"
  },
  "client": {
    "id": 42,
    "name": "Jane Smith",
    "email": "jane@example.com"
  },
  "thread": [
    {
      "id": 567,
      "type": "message",
      "created": "2025-05-24 12:34:56",
      "body": "I'm having an issue with my account",
      "user": {
        "id": 42,
        "name": "Jane Smith"
      },
      "attachments": [
        {
          "id": 321,
          "name": "screenshot.png",
          "size": 42356,
          "type": "image/png"
        }
      ]
    },
    {
      "id": 568,
      "type": "response",
      "created": "2025-05-24 13:22:44",
      "body": "Thank you for reporting this issue. We'll investigate.",
      "staff": {
        "id": 5,
        "name": "John Support"
      }
    }
  ]
}
```

## Example Usage

### cURL Example

```bash
curl -X GET \
  'http://your-osticket-install.com/api/tickets/number/ABC123.json' \
  -H 'X-API-Key: YOUR_API_KEY' \
  -H 'Accept: application/json'
```

### PHP Example

```php
$ticketNumber = 'ABC123';
$apiKey = 'YOUR_API_KEY';
$url = 'http://your-osticket-install.com/api/tickets/number/' . $ticketNumber . '.json';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'X-API-Key: ' . $apiKey,
    'Accept: application/json'
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode == 200) {
    $ticket = json_decode($response, true);
    // Process ticket data
} else {
    // Handle error
}

curl_close($ch);
```

## Error Responses

The API may return the following error responses:

| Status Code | Description                                |
|-------------|--------------------------------------------|
| 401         | API key not authorized                     |
| 404         | Unknown ticket (ticket number not found)   |
| 500         | Server error processing the request        |

## Notes

- Ticket numbers in osTicket are case-sensitive, so ensure you're using the exact format (typically uppercase letters and numbers)
- The API returns the full ticket thread history by default
- This endpoint provides the same level of detail as the ticket ID lookup endpoint
