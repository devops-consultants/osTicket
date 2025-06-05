# osTicket Client Reply API Documentation

## Overview

This API endpoint enables applications to simulate client/user email replies to tickets, making it possible to add thread entries that appear as if they came from the ticket owner or a collaborator. The API processes these replies through the same pipeline as actual email messages, ensuring consistent behavior and threading.

## Endpoint

```
POST /api/tickets/{ticketId}/add_thread.json
```

## Authentication

Authentication is done via API key using the `X-API-Key` header.

## Request Parameters

To simulate a client/user reply, include the following parameters in your request:

| Parameter    | Required | Description                                                              |
|--------------|----------|--------------------------------------------------------------------------|
| as_client    | Yes      | Set to `true` to indicate this is a client reply simulation              |
| message      | Yes      | Content of the reply message                                             |
| userId       | No       | User ID making the reply (defaults to ticket owner if not provided)      |
| attachments  | No       | Array of file attachments (see attachment format below)                  |

### Attachment Format

Each attachment in the `attachments` array should have the following structure:

```json
{
  "name": "filename.ext",
  "type": "mime/type",
  "data": "file_contents_or_base64_encoded_data",
  "encoding": "base64" // Optional, include if data is base64 encoded
}
```

## Response

Upon success, the API returns a JSON response with HTTP status code 201, containing information about the created thread entry:

```json
{
  "ticket_id": 1,
  "ticket_number": "ABC123",
  "thread_id": 42,
  "thread_type": "message",
  "message_id": "<random-message-id@domain.com>",
  "timestamp": "2025-01-01 12:34:56",
  "current_status": {
    "id": 1,
    "name": "Open",
    "state": "open"
  },
  "user": {
    "id": 5,
    "email": "user@example.com",
    "name": "John Doe"
  },
  "attachments": [
    {
      "id": 123,
      "name": "document.pdf"
    }
  ]
}
```

## Example Usage

### Basic Client Reply

```php
$data = array(
    'as_client' => true,
    'message' => 'This is a reply from the client.'
);
```

### Client Reply with Attachment

```php
$data = array(
    'as_client' => true,
    'message' => 'Please see the attached file.',
    'attachments' => array(
        array(
            'name' => 'document.pdf',
            'type' => 'application/pdf',
            'data' => base64_encode(file_get_contents('document.pdf')),
            'encoding' => 'base64'
        )
    )
);
```

### Reply as a Collaborator

```php
$data = array(
    'as_client' => true,
    'userId' => 2, // ID of the collaborator
    'message' => 'This is a reply from a collaborator.'
);
```

## Error Responses

The API may return the following error responses:

| Status Code | Description                                        |
|-------------|----------------------------------------------------|
| 400         | Missing required parameters or invalid input       |
| 401         | API key not authorized                             |
| 403         | User does not have access to the ticket            |
| 404         | Ticket or user not found                           |
| 500         | Server error processing the request                |

## Notes

- The API will properly handle threading and maintain conversation flow
- The reply will appear in the ticket history as coming from the client/collaborator
- Email notifications may be triggered based on system settings
