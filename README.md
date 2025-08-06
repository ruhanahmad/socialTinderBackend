# Laravel API Project

A Laravel-based REST API with user authentication functionality.

## Features

- User registration with email, password, country, and phone number
- User login with email and password
- JWT token-based authentication using Laravel Sanctum
- User profile retrieval
- Logout functionality

## API Endpoints

### Authentication Endpoints

### 1. User Registration
**POST** `/api/register`

**Request Body:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "country": "United States",
    "phone_number": "+1234567890"
}
```

**Response:**
```json
{
    "status": true,
    "message": "User registered successfully",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "country": "United States",
            "phone_number": "+1234567890"
        },
        "token": "1|abc123...",
        "token_type": "Bearer"
    }
}
```

### 2. User Login
**POST** `/api/login`

**Request Body:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "status": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "country": "United States",
            "phone_number": "+1234567890"
        },
        "token": "1|abc123...",
        "token_type": "Bearer"
    }
}
```

### 3. Get User Profile
**GET** `/api/profile`

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "status": true,
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "country": "United States",
            "phone_number": "+1234567890"
        }
    }
}
```

### 4. Logout
**POST** `/api/logout`

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "status": true,
    "message": "Logged out successfully"
}
```

### User Management Endpoints

### 5. Get Users by Country
**GET** `/api/users`

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `country` (optional): Filter by specific country

**Response:**
```json
{
    "status": true,
    "message": "Users retrieved successfully",
    "data": {
        "users": [
            {
                "id": 2,
                "name": "Jane Smith",
                "email": "jane@example.com",
                "country": "United States",
                "phone_number": "+1234567891",
                "profile_photo": "http://localhost:8000/storage/profile_photos/photo.jpg",
                "description": "Hello, I'm Jane!",
                "is_liked": false,
                "is_liked_by": true
            }
        ],
        "filtered_country": "United States"
    }
}
```

### 6. Update Profile
**PUT** `/api/profile`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body:**
```json
{
    "description": "Updated profile description",
    "profile_photo": [file upload]
}
```

**Response:**
```json
{
    "status": true,
    "message": "Profile updated successfully",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "country": "United States",
            "phone_number": "+1234567890",
            "profile_photo": "http://localhost:8000/storage/profile_photos/photo.jpg",
            "description": "Updated profile description"
        }
    }
}
```

### 7. Like/Dislike User
**POST** `/api/users/{userId}/like`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Body:**
```json
{
    "action": "like"
}
```

**Response:**
```json
{
    "status": true,
    "message": "User liked successfully",
    "data": {
        "action": "like",
        "target_user_id": 2
    }
}
```

### 8. Remove Like/Dislike
**DELETE** `/api/users/{userId}/like`

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "status": true,
    "message": "Like/dislike removed successfully",
    "data": {
        "target_user_id": 2
    }
}
```

### 9. Get My Likes
**GET** `/api/my-likes`

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "status": true,
    "message": "Likes retrieved successfully",
    "data": {
        "my_likes": [
            {
                "id": 2,
                "name": "Jane Smith",
                "email": "jane@example.com",
                "country": "United States",
                "profile_photo": "http://localhost:8000/storage/profile_photos/photo.jpg",
                "action": "like",
                "created_at": "2024-01-01T12:00:00.000000Z"
            }
        ],
        "liked_by": [
            {
                "id": 3,
                "name": "Bob Wilson",
                "email": "bob@example.com",
                "country": "Canada",
                "profile_photo": null,
                "action": "like",
                "created_at": "2024-01-01T12:30:00.000000Z"
            }
        ]
    }
}
```

### 10. Get Available Countries
**GET** `/api/countries`

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "status": true,
    "message": "Countries retrieved successfully",
    "data": {
        "countries": [
            "Canada",
            "United Kingdom",
            "United States"
        ]
    }
}
```

## Setup Instructions

### Prerequisites
- PHP 8.1 or higher
- Composer
- MySQL/PostgreSQL database

### Installation

1. **Install Dependencies**
   ```bash
   composer install
   ```

2. **Environment Setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database Configuration**
   Update your `.env` file with database credentials:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=laravel_api
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. **Run Migrations**
   ```bash
   php artisan migrate
   ```

5. **Create Storage Link** (for file uploads)
   ```bash
   php artisan storage:link
   ```

6. **Start Development Server**
   ```bash
   php artisan serve
   ```

## Testing the API

You can test the API endpoints using tools like Postman, cURL, or any API testing tool.

### Example cURL Commands

**Register a new user:**
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "country": "United States",
    "phone_number": "+1234567890"
  }'
```

**Login:**
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

**Get Profile (with token):**
```bash
curl -X GET http://localhost:8000/api/profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Get Users by Country:**
```bash
curl -X GET "http://localhost:8000/api/users?country=United%20States" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Update Profile:**
```bash
curl -X PUT http://localhost:8000/api/profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: multipart/form-data" \
  -F "description=My updated description" \
  -F "profile_photo=@/path/to/photo.jpg"
```

**Like a User:**
```bash
curl -X POST http://localhost:8000/api/users/2/like \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{"action": "like"}'
```

**Get My Likes:**
```bash
curl -X GET http://localhost:8000/api/my-likes \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## Validation Rules

- **Name**: Required, string, max 255 characters
- **Email**: Required, valid email format, unique in users table, max 255 characters
- **Password**: Required, string, minimum 8 characters
- **Country**: Required, string, max 255 characters
- **Phone Number**: Required, string, max 20 characters

## Error Responses

All endpoints return consistent error responses:

```json
{
    "status": false,
    "message": "Error message",
    "errors": {
        "field": ["Error description"]
    }
}
```

## Security Features

- Password hashing using Laravel's Hash facade
- JWT token-based authentication with Laravel Sanctum
- Input validation and sanitization
- CSRF protection (for web routes)
- Rate limiting (can be configured)

## File Structure

```
laravelApi/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── Controller.php
│   │       └── AuthController.php
│   └── Models/
│       └── User.php
├── database/
│   └── migrations/
│       └── 2014_10_12_000000_create_users_table.php
├── routes/
│   ├── api.php
│   ├── web.php
│   └── console.php
├── bootstrap/
│   └── app.php
├── composer.json
└── README.md
``` 