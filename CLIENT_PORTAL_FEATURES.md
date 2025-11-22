# Enhanced Client Portal Features

## Overview
The client portal has been enhanced with secure authentication, messaging system, and comprehensive order tracking.

## Key Features

### 1. National ID Login
- Clients can login using their **National ID** or **Passport Number**
- Separate login form for clients vs admin/staff
- Secure password authentication
- Optional Multi-Factor Authentication (MFA) support

### 2. Multi-Factor Authentication (MFA)
- Optional 2FA for enhanced security
- 6-digit verification codes
- Codes expire after 10 minutes
- Can be enabled per user account

### 3. Enhanced Order Information
Clients can view:
- **Order Summary**: Order ID, date, total price
- **Car Model Details**: Brand, model, year, trim level
- **VIN Number**: Assigned VIN for their specific vehicle
- **Container Information**: Container ID and number
- **Real-time Tracking**: Direct links to searates.com container tracking
- **Payment Progress**: Visual progress bar showing payment status
- **Shipping Status**: Current status with timeline

### 4. Messaging System
- **Two-way Communication**: Clients can send messages to admin/support
- **Order-linked Messages**: Messages can be linked to specific orders
- **File Attachments**: Upload documents, images, invoices
- **Read Receipts**: Track when messages are read
- **Audit Trail**: All messages timestamped and logged
- **Unread Notifications**: Badge showing unread message count

### 5. Container Tracking
- **Direct Tracking Links**: Click to track container on searates.com
- **Format**: `https://www.searates.com/container/tracking/?number=[CONTAINER_NUMBER]`
- **Real-time Status**: See container status and arrival dates

### 6. Role-Based Access
- Clients **only** see their own data
- Data filtered by client's National ID
- Secure session management
- Automatic logout on unauthorized access

### 7. Mobile-Responsive Design
- Fully responsive layout
- Works on desktop, tablet, and mobile
- Touch-friendly interface
- Optimized for small screens

## Database Tables

### Messages Table
- Stores all client-admin communications
- Links to orders and clients
- Tracks read/unread status
- Timestamps for audit trail

### MFA Codes Table
- Stores verification codes
- Expiration tracking
- One-time use codes

## Setup Instructions

### 1. Create Client User Accounts
When adding a client, check "Create user account for client portal access" and set a password.

### 2. Enable MFA (Optional)
- Go to Users management
- Edit user account
- Enable MFA (requires additional setup for SMS/Email)

### 3. Client Login
- Go to login page
- Click "Client Portal" tab
- Enter National ID/Passport and password
- Complete MFA if enabled

## Security Features

1. **Password Hashing**: All passwords stored as bcrypt hashes
2. **Session Security**: Secure session management
3. **SQL Injection Protection**: Prepared statements
4. **Input Sanitization**: All inputs sanitized
5. **Role-Based Access**: Clients can only access their data
6. **Audit Trail**: All communications logged with timestamps

## File Structure

```
/
├── client_portal.php      # Enhanced client portal
├── messages.php          # Client messaging interface
├── order_view.php        # Detailed order view
├── functions_messaging.php # Messaging functions
└── Admin/
    └── messages.php      # Admin message management
```

## Usage

### For Clients:
1. Login with National ID/Passport
2. View orders and track shipping
3. Send messages to support
4. Upload documents/invoices
5. Track container shipments

### For Admins:
1. View all client messages
2. Reply to client inquiries
3. Attach files to responses
4. Mark messages as read
5. Filter by unread messages

## Notes

- MFA codes are currently displayed on screen (for testing). In production, send via SMS/Email.
- File uploads limited to 5MB per file
- Allowed file types: PDF, JPG, JPEG, PNG, DOC, DOCX
- Messages are permanently stored for audit purposes

