# osTicket Staff API Documentation

This document describes the Staff API endpoints that have been added to osTicket to support managing agents/staff members programmatically.

## Authentication

All API endpoints require a valid API key to be sent in the `X-API-Key` HTTP header.

```
X-API-Key: YOUR_API_KEY_HERE
```

**Important**: The API key must have the **"Can Create/Manage Agents"** permission enabled in the osTicket admin panel. This is a separate permission from the existing ticket and cron permissions.

### Configuring API Key Permissions

1. Log into the osTicket admin panel
2. Navigate to **Manage** → **API Keys**
3. Create a new API key or edit an existing one
4. In the **Services** section, check the **"Can Create/Manage Agents (Staff Management)"** checkbox
5. Save the API key configuration

## Base URL

```
http://your-osticket-domain/api
```

## Endpoints

### 1. List All Staff Members

**GET** `/staff.json`

Returns a paginated list of all staff members in the system.

#### Query Parameters

- `page` (optional): Page number for pagination (default: 1)
- `limit` (optional): Number of results per page (default: 25, max: 100)
- `active` (optional): Filter by active status (true/false)

#### Example Request

```bash
curl -X GET "http://your-domain/api/staff.json?page=1&limit=10&active=true" \
  -H "X-API-Key: YOUR_API_KEY"
```

#### Example Response

```json
{
  "staff": [
    {
      "id": 1,
      "email": "admin@example.com",
      "username": "admin",
      "firstname": "Admin",
      "lastname": "User",
      "name": "Admin User",
      "phone": "555-1234",
      "mobile": "555-5678",
      "signature": "Best regards,\nAdmin Team",
      "timezone": "America/New_York",
      "lang": "en_US",
      "isactive": 1,
      "isadmin": 1,
      "created": "2023-01-01 00:00:00",
      "updated": "2023-06-01 12:00:00",
      "lastlogin": "2023-06-21 09:30:00",
      "department": {
        "id": 1,
        "name": "Support"
      },
      "role": {
        "id": 1,
        "name": "All Access"
      }
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "count": 1
  }
}
```

### 2. Find Staff Member by Email

**GET** `/staff/email/{email}.json`

Returns details of a specific staff member by their email address.

#### Path Parameters

- `email`: The email address of the staff member to find

#### Example Request

```bash
curl -X GET "http://your-domain/api/staff/email/admin@example.com.json" \
  -H "X-API-Key: YOUR_API_KEY"
```

#### Example Response

```json
{
  "id": 1,
  "email": "admin@example.com",
  "username": "admin",
  "firstname": "Admin",
  "lastname": "User",
  "name": "Admin User",
  "phone": "555-1234",
  "mobile": "555-5678",
  "signature": "Best regards,\nAdmin Team",
  "timezone": "America/New_York",
  "lang": "en_US",
  "isactive": 1,
  "isadmin": 1,
  "created": "2023-01-01 00:00:00",
  "updated": "2023-06-01 12:00:00",
  "lastlogin": "2023-06-21 09:30:00",
  "department": {
    "id": 1,
    "name": "Support"
  },
  "role": {
    "id": 1,
    "name": "All Access"
  }
}
```

### 3. Get Staff Member by ID

**GET** `/staff/{id}.json`

Retrieves details of a specific staff member by their ID.

#### URL Parameters

- `id`: The staff member's ID

#### Example Request

```bash
curl -X GET "http://your-domain/api/staff/5.json" \
  -H "X-API-Key: YOUR_API_KEY"
```

#### Example Response

```json
{
  "id": 5,
  "email": "john.doe@example.com",
  "username": "johndoe",
  "firstname": "John",
  "lastname": "Doe",
  "name": "John Doe",
  "phone": "555-1234",
  "mobile": null,
  "signature": "",
  "timezone": "America/New_York",
  "lang": "en_US",
  "isactive": 1,
  "isadmin": 0,
  "isvisible": 1,
  "onvacation": 0,
  "assigned_only": 0,
  "created": "2023-06-21 10:00:00",
  "updated": "2023-06-21 10:00:00",
  "lastlogin": null,
  "department": {
    "id": 1,
    "name": "Support"
  },
  "role": {
    "id": 2,
    "name": "Agent"
  },
  "teams": [
    {
      "id": 1,
      "name": "Level 1 Support"
    }
  ],
  "dept_access": [
    {
      "id": 1,
      "name": "Support"
    },
    {
      "id": 2,
      "name": "Sales"
    }
  ]
}
```

### 4. Create New Staff Member

**POST** `/staff.json`

Creates a new staff member in the system.

#### Request Body

The request body should be JSON containing the staff member details:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `firstname` | string | Yes | First name of the staff member |
| `lastname` | string | Yes | Last name of the staff member |
| `email` | string | Yes | Email address (must be unique) |
| `username` | string | Yes | Username for login (must be unique) |
| `passwd` | string | No | Password for the account |
| `passwd2` | string | No | Password confirmation (must match passwd) |
| `phone` | string | No | Phone number |
| `mobile` | string | No | Mobile phone number |
| `signature` | string | No | Email signature |
| `timezone` | string | No | Timezone (e.g., "America/New_York") |
| `dept_id` | integer | No | Department ID (default: 1) |
| `role_id` | integer | No | Role ID (default: 1) |
| `isactive` | boolean | No | Whether the account is active (default: true) |
| `isadmin` | boolean | No | Whether the user has admin privileges (default: false) |
| `welcome_email` | boolean | No | Send welcome email (default: false) |
| `notes` | string | No | Internal notes about the staff member |
| `lang` | string | No | Language preference (default: auto-detected from Accept-Language header or system default) |

#### Example Request

```bash
curl -X POST "http://your-domain/api/staff.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "firstname": "John",
    "lastname": "Doe",
    "email": "john.doe@example.com",
    "username": "johndoe",
    "passwd": "SecurePassword123!",
    "passwd2": "SecurePassword123!",
    "phone": "555-1234",
    "dept_id": 1,
    "role_id": 2,
    "isactive": true,
    "notes": "New support agent"
  }'
```

#### Example Response

```json
{
  "id": 5,
  "email": "john.doe@example.com",
  "username": "johndoe",
  "firstname": "John",
  "lastname": "Doe",
  "name": "John Doe",
  "phone": "555-1234",
  "mobile": null,
  "signature": "",
  "timezone": "America/New_York",
  "lang": "en_US",
  "isactive": 1,
  "isadmin": 0,
  "created": "2023-06-21 10:00:00",
  "updated": "2023-06-21 10:00:00",
  "lastlogin": null,
  "department": {
    "id": 1,
    "name": "Support"
  },
  "role": {
    "id": 2,
    "name": "Agent"
  }
}
```

### 5. Update Existing Staff Member

**POST** `/staff/{id}.json`

Updates an existing staff member. This endpoint allows you to modify various aspects of a staff member including:
- Administrator privileges (`isadmin`)
- Primary department assignment (`dept_id`)
- Role and permissions (`role_id`) 
- Team assignments (`teams`)
- Department access (`dept_access`)
- Personal information and settings

#### URL Parameters

- `id`: The staff member's ID to update

#### Request Body

The request body should be JSON containing the fields to update. You only need to include the fields you want to change:

| Field | Type | Description |
|-------|------|-------------|
| `firstname` | string | First name of the staff member |
| `lastname` | string | Last name of the staff member |
| `email` | string | Email address (must be unique) |
| `username` | string | Username for login (must be unique) |
| `passwd` | string | New password for the account |
| `passwd2` | string | Password confirmation (must match passwd) |
| `phone` | string | Phone number |
| `mobile` | string | Mobile phone number |
| `signature` | string | Email signature |
| `timezone` | string | Timezone (e.g., "America/New_York") |
| `dept_id` | integer | Primary department ID |
| `role_id` | integer | Role ID |
| `isactive` | boolean | Whether the account is active |
| `isadmin` | boolean | Whether the user has admin privileges |
| `isvisible` | boolean | Whether the user is visible in staff lists |
| `onvacation` | boolean | Whether the user is on vacation |
| `assigned_only` | boolean | Whether user only sees assigned tickets |
| `notes` | string | Internal notes about the staff member |
| `lang` | string | Language preference |
| `teams` | array | Array of team IDs to assign |
| `dept_access` | array | Array of department IDs for extended access |

#### Example Request - Toggle Admin Status

```bash
curl -X POST "http://your-domain/api/staff/5.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "isadmin": true,
    "notes": "Promoted to administrator on 2023-06-21"
  }'
```

#### Example Request - Update Department and Team Assignment

```bash
curl -X POST "http://your-domain/api/staff/5.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "dept_id": 2,
    "role_id": 3,
    "teams": [1, 2],
    "dept_access": [1, 2, 3]
  }'
```

#### Example Response

```json
{
  "id": 5,
  "email": "john.doe@example.com",
  "username": "johndoe",
  "firstname": "John",
  "lastname": "Doe",
  "name": "John Doe",
  "phone": "555-1234",
  "mobile": null,
  "signature": "",
  "timezone": "America/New_York",
  "lang": "en_US",
  "isactive": 1,
  "isadmin": 1,
  "isvisible": 1,
  "onvacation": 0,
  "assigned_only": 0,
  "created": "2023-06-21 10:00:00",
  "updated": "2023-06-21 15:30:00",
  "lastlogin": null,
  "department": {
    "id": 2,
    "name": "Technical Support"
  },
  "role": {
    "id": 3,
    "name": "Senior Agent"
  },
  "teams": [
    {
      "id": 1,
      "name": "Level 1 Support"
    },
    {
      "id": 2,
      "name": "Level 2 Support"
    }
  ],
  "dept_access": [
    {
      "id": 1,
      "name": "Support"
    },
    {
      "id": 2,
      "name": "Technical Support"
    },
    {
      "id": 3,
      "name": "Sales"
    }
  ]
}
```

### Field Value Resolution

The Staff API now supports both numeric IDs and string names for the following fields:

- **`dept_id`**: Can accept either a department ID (e.g., `1`) or department name (e.g., `"Support"`)
- **`role_id`**: Can accept either a role ID (e.g., `2`) or role name (e.g., `"Agent"`) 
- **`teams`**: Array can contain team IDs (e.g., `[1, 2]`) or team names (e.g., `["Level 1 Support", "Level 2 Support"]`)
- **`dept_access`**: Array can contain department IDs or department names

**Examples:**

```json
{
  "dept_id": "Support",
  "role_id": "Agent", 
  "teams": ["Level 1 Support", "Level 2 Support"],
  "dept_access": ["Support", "Sales"]
}
```

Is equivalent to:

```json
{
  "dept_id": 1,
  "role_id": 2,
  "teams": [1, 2], 
  "dept_access": [1, 3]
}
```

If an invalid name is provided, the API will return a 400 error with details about which name could not be resolved.

## Error Responses

All endpoints return appropriate HTTP status codes and error messages:

### 400 Bad Request

```json
{
  "error": "Validation error message"
}
```

### 401 Unauthorized

```json
{
  "error": "API key not authorized"
}
```

or

```json
{
  "error": "API key not authorized for agent management"
}
```

This error occurs when:
- The API key is invalid or missing
- The API key doesn't have the "Can Create/Manage Agents" permission enabled

### 404 Not Found

```json
{
  "error": "Staff member not found"
}
```

### 500 Internal Server Error

```json
{
  "error": "Unable to create staff member"
}
```

## Testing

A test script is provided at `/api/test-staff-api.php` to help verify the API endpoints are working correctly. Make sure to:

1. Configure a valid API key in osTicket admin panel
2. Update the `$api_key` variable in the test script
3. Run the test script: `php test-staff-api.php`

## Notes

- All timestamps are returned in the format configured in osTicket
- The `name` field is automatically generated from `firstname` and `lastname`
- Password fields are not returned in API responses for security
- Inactive staff members are included in list results unless filtered out
- Department and role information is included as nested objects when available
