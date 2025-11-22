# Fix Admin Password Issue

If you're getting "Invalid username or password" when trying to login with `admin` / `admin123`, follow one of these solutions:

## Solution 1: Run the Fix Script (Easiest)

1. Open your browser and go to:
   ```
   http://localhost/CARS2/fix_password.php
   ```

2. Click the "Fix Admin Password" button

3. Try logging in again with:
   - Username: `admin`
   - Password: `admin123`

## Solution 2: Run SQL Directly

1. Open phpMyAdmin (http://localhost/phpmyadmin)

2. Select the `car_dealership` database

3. Go to the SQL tab

4. Run this SQL command:
   ```sql
   UPDATE users 
   SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
   WHERE username = 'admin';
   ```

5. Try logging in again

## Solution 3: Generate New Hash (If above don't work)

If the above solutions don't work, create a new PHP file `generate_hash.php`:

```php
<?php
echo password_hash('admin123', PASSWORD_DEFAULT);
?>
```

Run it via browser, copy the hash, then update the database:
```sql
UPDATE users SET password = 'PASTE_HASH_HERE' WHERE username = 'admin';
```

## Verify the Fix

After running any solution, verify by checking:
1. The password hash in the database matches a valid bcrypt hash
2. You can login with admin/admin123

## Security Note

**IMPORTANT:** Change the default password immediately after first login in production!

