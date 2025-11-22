# Quick Setup: Media System Database Tables

## 🚀 Quick Start (3 Steps)

### Step 1: Create Database Tables
Open your browser and navigate to:
```
http://localhost/CARS2/setup_media.html
```

**What this does:**
- Creates `order_media` table (stores media files metadata)
- Creates `media_logs` table (audit trail)
- Creates required indexes
- Creates upload directories

### Step 2: Test Upload
1. Log in to admin panel: `http://localhost/CARS2/Admin/index.php`
2. Go to: **Orders → Click any order → Scroll down to "Media Gallery"**
3. Click "Upload Media" button
4. Select a test photo (JPG/PNG) or video (MP4)
5. Click "Upload Files"

**Expected Result:**
- File appears in gallery with thumbnail
- File size and upload time shown
- Delete button available

### Step 3: Test Client View
1. Log out of admin
2. Log in as client (e.g., CLI001 with passport number)
3. Go to: **Client Portal → Click an order with media**
4. Scroll to "Vehicle Media" section
5. Click media to view/download

**Expected Result:**
- Media displays in gallery
- Can download or stream
- Only their own order's media visible

---

## 📋 Troubleshooting

### Issue: Setup Page Shows Error
**Solution:**
1. Ensure database is running
2. Check `config.php` has correct credentials
3. Verify `orders` and `users` tables exist
4. Check database user has CREATE TABLE permission

### Issue: Table Already Exists Error
**Solution:** The tables already exist (this is fine). You can:
- Skip setup and go directly to testing
- Or delete tables and recreate: 
  ```sql
  DROP TABLE IF EXISTS media_logs;
  DROP TABLE IF EXISTS order_media;
  ```

### Issue: Upload Directory Not Found
**Solution:** Create manually:
```bash
mkdir -p uploads/media/orders/thumbs
chmod 755 uploads/media
chmod 755 uploads/media/orders
chmod 755 uploads/media/orders/thumbs
```

---

## 📁 Files Involved

| File | Purpose |
|------|---------|
| `setup_media.html` | Browser-based setup wizard |
| `create_media_tables_ajax.php` | AJAX endpoint that creates tables |
| `functions_media.php` | Core media functions library |
| `serve_media.php` | Secure media delivery endpoint |
| `Admin/order_details.php` | Admin media upload interface |
| `order_view.php` | Client media viewing |

---

## ✅ After Setup

Once tables are created:

1. **For Admins:**
   - Upload media to orders (Admin → Orders → Order Details)
   - Bulk upload multiple files at once
   - Delete media with one click
   - View upload history with timestamps

2. **For Clients:**
   - View all media for their orders
   - Download or stream videos
   - See file sizes and descriptions
   - No access to other clients' media

3. **For System:**
   - Access logging (who viewed what, when)
   - Automatic thumbnail generation
   - Video streaming with range support
   - Secure file delivery

---

## 🔍 Verify Setup

To verify tables were created:

**Via phpMyAdmin:**
1. Open phpMyAdmin
2. Select database: `car_dealership`
3. Look for tables: `order_media` and `media_logs`
4. Both should be green (active)

**Via MySQL:**
```sql
SHOW TABLES LIKE 'order_media';
SHOW TABLES LIKE 'media_logs';
DESC order_media;
```

---

## 📚 Next Steps

1. Read `MEDIA_SYSTEM.md` for complete documentation
2. Follow `SETUP_MEDIA.md` for detailed testing
3. Check `IMPLEMENTATION_SUMMARY.md` for API reference

---

**Status:** Ready for testing!
