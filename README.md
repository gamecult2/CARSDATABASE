# Car Dealership Client and Logistics Management System

A comprehensive web-based database system for managing the full lifecycle of car imports from client order to final delivery.

## Features

### Core Functionality
- **Client Management**: Complete CRUD operations with search, filter, and order history
- **Car Inventory Management**: Track cars with VIN, pricing, and status
- **Order Management**: Create orders linking clients to cars with payment tracking
- **Container/Voyage Management**: Track shipping containers with 4-car capacity limit
- **Payment Tracking**: Record multiple payments per order with automatic balance calculation
- **Shipping Status Tracking**: Real-time status updates from production to delivery
- **Financial Dashboard**: Revenue, outstanding payments, and profitability analysis

### Advanced Features
- **Client Portal**: Secure login for clients to view orders, payments, and shipping status
- **Document Management**: Upload and attach documents to clients, cars, orders, and containers
- **Media Management**: Secure upload and management of order media (photos and videos)
  - Admins can upload bulk media files for each order
  - Clients can view/download media from their orders
  - Automatic thumbnail generation for photos
  - Video streaming with range request support
  - Role-based access control and audit logging
- **Overdue Payment Reports**: Automatic detection of overdue payments
- **Cost Per Car Calculation**: Automatic calculation of shipping cost per car for profitability
- **Container Allocation**: Allocate up to 4 cars per container with validation

## Installation

### Requirements
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server
- WAMP/LAMP/XAMPP (for local development)

### Setup Steps

1. **Database Setup**
   ```sql
   -- Create database
   CREATE DATABASE car_dealership;
   
   -- Import schema
   mysql -u root -p car_dealership < database.sql
   ```

2. **Configuration**
   - Edit `config.php` and update database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'car_dealership');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

3. **File Permissions**
   - Ensure the `uploads/` directory is writable:
     ```bash
     chmod 755 uploads/
     ```

4. **Access the Application**
   - Admin: `http://localhost/CARS2/login.php`
   - Default credentials:
     - Username: `admin`
     - Password: `admin123`
     - **Change this password immediately in production!**

## Database Schema

### Main Tables
- **clients**: Client information and contact details
- **cars**: Car inventory with VIN, pricing, and status
- **containers**: Shipping container/voyage information
- **orders**: Links clients to cars with order details
- **payments**: Payment records linked to orders
- **container_cars**: Many-to-many relationship for car allocation
- **users**: System users (admin, sales agents, clients)
- **documents**: File uploads linked to entities

## User Roles

1. **Admin**: Full access to all features
2. **Sales Agent**: Can manage clients, cars, orders, and payments
3. **Client**: Access to client portal to view their orders and payments

## Workflow

### Order Creation
1. Create or select a client
2. Select an available car from inventory
3. Set order price and optional deposit
4. System automatically creates order and updates car status

### Container Allocation
1. Create a container with shipping details
2. Allocate up to 4 cars to the container
3. System automatically updates order shipping status
4. Cost per car is calculated automatically

### Payment Tracking
1. Record payments against orders
2. System calculates remaining balance automatically
3. View overdue payments report

### Shipping Status Updates
- Awaiting Container
- Shipped on Container
- Arrived in Algeria
- Customs Cleared
- Ready for Pickup
- Delivered

### Media Management
Admins can upload photos and videos directly to orders:
1. Open order details
2. Scroll to "Media Gallery"
3. Click "Upload Media"
4. Select one or multiple files (photos/videos)
5. Files appear in gallery for clients to view

**See MEDIA_SYSTEM.md for detailed documentation**

## File Structure

```
/
├── config.php              # Database configuration
├── functions.php           # Common functions
├── functions_media.php     # Media management functions
├── login.php              # Login page
├── logout.php             # Logout handler
├── client_portal.php      # Client portal
├── order_view.php         # Client order view
├── serve_media.php        # Secure media delivery
├── database.sql           # Database schema
├── MEDIA_SYSTEM.md        # Media system documentation
├── SETUP_MEDIA.md         # Media setup & testing guide
├── Admin/
│   ├── index.php         # Admin dashboard
│   ├── clients.php       # Client management
│   ├── cars.php          # Car inventory
│   ├── orders.php        # Order management
│   ├── containers.php    # Container management
│   ├── payments.php      # Payment management
│   ├── financial_dashboard.php
│   ├── order_details.php # Order details with media
│   ├── delete_media.php  # Media deletion handler
│   └── includes/
│       ├── header.php
│       └── sidebar.php
└── uploads/              # File uploads (documents & media)
    └── media/
        └── orders/       # Order media storage
            └── thumbs/   # Photo thumbnails
```

## Security Features

- Password hashing using PHP `password_hash()`
- Prepared statements (PDO) to prevent SQL injection
- Input sanitization
- Session management
- Role-based access control
- File upload validation
- Secure media delivery with access control
- Media access audit logging
- MIME type enforcement for uploaded files

## Media Management (NEW)

### Supported Formats
- **Photos**: JPG, JPEG, PNG, WebP
- **Videos**: MP4, WebM, MOV, AVI, MKV
- **Max Size**: 100MB per file

### Features
- Bulk upload support
- Automatic thumbnail generation for photos
- Video streaming with HTTP range support
- Role-based access control
- Access logging for audit trail
- Secure file delivery endpoint
- Client can only view their own order media

**For setup instructions**: See SETUP_MEDIA.md
**For detailed documentation**: See MEDIA_SYSTEM.md

## Sample Data

The database includes sample data for testing:
- 18 clients (CLI001-CLI018)
- 28 cars (various brands and models)
- 3 containers
- 3 orders
- 4 payments

## Notes

- Default admin password is `admin123` - **change this in production!**
- Document file uploads limited to 5MB
- Media file uploads limited to 100MB per file
- Allowed file types: PDF, JPG, JPEG, PNG, DOC, DOCX (documents)
- Media formats: JPG, JPEG, PNG, WebP, MP4, WebM, MOV, AVI, MKV
- Container capacity is fixed at 4 cars
- Timezone is set to Africa/Algiers
- GD library recommended for photo thumbnails (optional)

## Support

For issues or questions, please contact the system administrator.

**Media System**: See MEDIA_SYSTEM.md and SETUP_MEDIA.md


## License

This system is proprietary software. All rights reserved.

