# osTicket Thread API

This document describes how to use the osTicket API to add new threads (replies or notes) to existing tickets.

## Adding a Thread Entry

**Endpoint:** `POST /api/tickets/{id}/add_thread.json`

This endpoint allows you to post new replies, internal notes, or simulated client/user email replies to existing tickets. There are three types of thread entries you can create:

1. **Internal Notes** (`thread_type=note`): Staff-only notes not visible to clients
2. **Staff Responses** (`thread_type=response`): Public replies from staff members
3. **Client/User Replies** (`thread_type=email`): Simulates an email reply from a client/user

### Authentication

Authentication is done via API keys. Include the API key in the `X-API-Key` HTTP header.

Example:
```
X-API-Key: 184F66085DA76CDF5300FE2C6E229238
```

### Request Format

The API accepts both JSON and XML formats. Make sure to set the appropriate Content-Type header:
- `application/json` for JSON
- `application/xml` for XML

### Request Parameters

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| thread_type | string | Type of thread entry: 'note' for internal note, 'response' for public reply, 'email' for client/user reply simulation | Yes |
| message | string | Content of the thread entry | Yes (unless using cannedId) |
| staffId | integer | ID of staff member making the post (not used for 'email' type) | No |
| alert | boolean | Whether to alert participants | No (default: true) |
| poster | string | Name of the poster (default: 'API', not used for 'email' type) | No |
| cannedId | integer | ID of canned response to use (not used for 'email' type) | No |
| cannedMode | string | How to use canned response: 'prepend', 'append', 'replace' | No (default: 'replace') |
| cannedAttachments | boolean | Whether to include canned response attachments | No (default: false) |
| signature | string | Signature selection: 'none', 'mine', 'dept' (not used for 'email' type) | No (default: 'none') |
| includeSignature | boolean | Whether to include signature (not used for 'email' type) | No (default: false) |
| status_id | integer | ID of the status to set the ticket to (not used for 'email' type) | No |
| userId | integer | ID of the user making the reply (only for 'email' thread type) | No (default: ticket owner) |
| as_client | boolean | Legacy parameter for backward compatibility (sets thread_type to 'email' when true) | No |
| attachments | array | Array of file attachments | No |

#### Attachment Format

Each attachment in the attachments array should have the following structure:

```json
{
  "name": "filename.ext",
  "type": "application/mime-type",
  "data": "file-content-here",
  "encoding": "base64"
}
```

### Examples

#### Adding an Internal Note

```json
POST /api/tickets/123/add_thread.json
X-API-Key: 184F66085DA76CDF5300FE2C6E229238
Content-Type: application/json

{
  "thread_type": "note",
  "message": "This is an internal note visible only to staff",
  "staffId": 1,
  "alert": false
}
```

#### Adding a Reply with Status Change

```json
POST /api/tickets/123/add_thread.json
X-API-Key: 184F66085DA76CDF5300FE2C6E229238
Content-Type: application/json

{
  "thread_type": "response",
  "message": "Thank you for your inquiry. Your issue has been resolved.",
  "staffId": 1,
  "status_id": 3,
  "includeSignature": true,
  "signature": "mine"
}
```

#### Using a Canned Response with Attachments

```json
POST /api/tickets/123/add_thread.json
X-API-Key: 184F66085DA76CDF5300FE2C6E229238
Content-Type: application/json

{
  "thread_type": "response",
  "cannedId": 5,
  "cannedMode": "append", 
  "message": "In addition to our standard response, please note...",
  "cannedAttachments": true,
  "staffId": 1,
  "alert": true
}
```

#### Adding a Reply with an Attachment

```json
POST /api/tickets/123/add_thread.json
X-API-Key: 184F66085DA76CDF5300FE2C6E229238
Content-Type: application/json

{
  "thread_type": "response",
  "message": "Please find attached the requested document.",
  "staffId": 1,
  "attachments": [
    {
      "name": "document.pdf",
      "type": "application/pdf",
      "data": "JVBERi0xLjUKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nGVOuwoCMRDs8xVbC8aZvO4SrhMU9NqzOCyuEBG08P...",
      "encoding": "base64"
    }
  ]
}
```

#### Simulating a Client/User Reply (Email)

The 'email' thread type simulates a user sending an email reply to the ticket. This is useful for integrations that need to add replies on behalf of clients or users. Unlike the 'note' and 'response' thread types which are posted by staff members, the 'email' thread type is associated with a user (either the ticket owner or a specified user).

```json
POST /api/tickets/123/add_thread.json
X-API-Key: 184F66085DA76CDF5300FE2C6E229238
Content-Type: application/json

{
  "thread_type": "email",
  "message": "Thank you for your response. I have another question...",
  "userId": 5,
  "attachments": [
    {
      "name": "screenshot.png",
      "type": "image/png",
      "data": "iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAApgAAAKYB3X3/OAAAABl0RVh0U...",
      "encoding": "base64"
    }
  ]
}
```

### Response

Upon successful creation of a thread entry, the API will respond with a 201 Created status and a JSON or XML response containing details about the created thread.

#### JSON Response Example for Staff Entry (note/response):
```json
{
  "ticket_id": 123,
  "ticket_number": "ABC-123-4567",
  "thread_id": 456,
  "thread_type": "response",
  "message_id": "<abc123@osticket.com>",
  "timestamp": "2023-05-24 10:30:00",
  "current_status": {
    "id": 3,
    "name": "Closed",
    "state": "closed"
  },
  "attachments": [
    {
      "id": 789,
      "name": "document.pdf"
    }
  ]
}
```

#### JSON Response Example for Client/User Reply (email):
```json
{
  "ticket_id": 123,
  "ticket_number": "ABC-123-4567",
  "thread_id": 457,
  "thread_type": "email",
  "message_id": "<def456@osticket.com>",
  "timestamp": "2023-05-24 11:15:00",
  "current_status": {
    "id": 1,
    "name": "Open",
    "state": "open"
  },
  "user": {
    "id": 5,
    "name": "John Doe",
    "email": "johndoe@example.com"
  },
  "attachments": [
    {
      "id": 790,
      "name": "screenshot.png"
    }
  ]
}
```

### Error Responses

The API will return appropriate HTTP status codes for different error conditions:

- 400 Bad Request: Invalid request parameters
- 401 Unauthorized: Invalid API key
- 404 Not Found: Ticket not found
- 500 Internal Server Error: Server error when processing the request

Error response format:
```json
{
  "error": "Error message here"
}
```
