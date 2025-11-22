# Database Migration Notes

## Major Changes Made

### 1. VIN Moved from Cars to Orders
- **Before**: VIN was stored in `cars` table (car inventory)
- **After**: VIN is stored in `orders` table (order-specific)
- **Reason**: VIN is order-specific and reflects the exact unit delivered to the client

### 2. Car Inventory is Now Model-Based
- **Before**: Each car had a unique VIN
- **After**: Car inventory contains unique model-year-trim combinations only
- **Fields**: brand, model, year, trim (optional)
- **Unique Constraint**: (brand, model, year, trim)

### 3. All Prices in USD
- **Before**: Purchase price in CNY, Sale price in DZD
- **After**: Both prices in USD
- **Fields**: `purchase_price_usd`, `sale_price_usd`

### 4. Container Tracking Links
- **New Field**: `container_number` in `containers` table
- **Tracking URL Format**: `https://www.searates.com/container/tracking/?number=[CONTAINER_NUMBER]`
- **Display**: Shows clickable tracking link when container_number is available

### 5. Pre-populated Car Models
- Global best-sellers: Toyota Corolla, RAV4, Honda CR-V, Ford F-150, Tesla Model Y
- UAE favorites: Toyota Land Cruiser, Nissan Patrol, Lexus GX/RX, Mercedes-Benz GLE/E-Class, BMW X5/5 Series, Range Rover Sport
- Years: 2023-2024
- Includes trim levels where applicable

## Files Updated

### Database
- `database.sql` - Complete schema update

### Core Functions
- `functions.php` - Updated currency formatting to USD, added tracking link function

### Car Management (Model-Based, No VIN)
- `Admin/add_car.php` - Removed VIN, added trim, USD prices
- `Admin/edit_car.php` - Needs update
- `Admin/cars.php` - Needs update (remove VIN column)
- `Admin/car_details.php` - Needs update (remove VIN)

### Order Management (VIN Added)
- `Admin/add_order.php` - Needs update (add VIN field)
- `Admin/order_details.php` - Needs update (show VIN from order)
- `Admin/orders.php` - Needs update (show VIN from order)

### Container Management (Tracking Links)
- `Admin/add_container.php` - Needs update (add container_number)
- `Admin/edit_container.php` - Needs update (add container_number)
- `Admin/container_details.php` - Needs update (show tracking link)
- `Admin/containers.php` - Needs update (show tracking link)

### Client Portal
- `client_portal.php` - Needs update (VIN from order)
- `order_view.php` - Needs update (VIN from order)

## Migration Steps

1. **Backup existing database**
2. **Run updated database.sql** (drops and recreates tables)
3. **Update all PHP files** to match new schema
4. **Test all functionality**

## Important Notes

- VIN is now optional on orders (can be added later when vehicle is assigned)
- Car inventory shows models only, not individual vehicles
- All prices display in USD format: $XX,XXX.XX
- Container tracking links are generated automatically when container_number is set

