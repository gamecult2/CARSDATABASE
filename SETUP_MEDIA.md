# Media Management System - Setup & Testing Guide

## Quick Setup

### Step 1: Import Database Changes

Run the updated `database.sql` to add the new `order_media` table:

```bash
mysql -u root -p car_dealership < database.sql
```

Or import via phpMyAdmin:
1. Go to phpMyAdmin
2. Select database `car_dealership`
3. Click "Import"
4. Select `database.sql`
5. Click "Go"

### Step 2: Verify Directory Structure

The following directories should exist (auto-created by config.php):

```
/uploads/
├── media/
│   └── orders/
│       └── thumbs/
├── orders/
├── clients/
├── cars/
├── containers/
└── messages/
```

If not created automatically, create manually:
```bash
mkdir -p /uploads/media/orders/thumbs
chmod 755 /uploads/media/orders
chmod 755 /uploads/media/orders/thumbs
```

### Step 3: Verify File Permissions

Ensure write permissions on media directory:

```bash
chmod 777 /uploads/media
chmod 777 /uploads/media/orders
chmod 777 /uploads/media/orders/thumbs
```

### Step 4: Enable GD Library (Optional but Recommended)

For thumbnail generation, ensure GD library is enabled:

**Windows (WAMP)**:
1. Edit `php.ini`
2. Uncomment: `;extension=gd` → `extension=gd`
3. Restart Apache

**Linux**:
```bash
sudo apt-get install php-gd
sudo systemctl restart apache2
```

Verify:
```bash
php -m | grep -i gd
```

### Step 5: Test Installation

Navigate to an order in the admin panel:
- Admin → Orders → Click any order
- Should see "Media Gallery" section with upload button
- Try uploading a test image or video

---

## Testing Checklist

### Test 1: Admin Upload

**Steps**:
1. Log in as admin
2. Go to Admin → Orders → Click ORD001
3. Scroll to "Media Gallery" section
4. Click "Upload Media" button
5. Select a JPG/MP4 file
6. Click "Upload Files"

**Expected Results**:
- ✓ Modal closes
- ✓ Success message appears
- ✓ Media gallery refreshes
- ✓ Thumbnail appears (if photo)
- ✓ File size shows

**Testing Files** (create if needed):
- Test image: Any JPG/PNG (200KB-5MB)
- Test video: Any MP4 (10-50MB recommended)

### Test 2: Bulk Upload

**Steps**:
1. Same as Test 1
2. Select 3-5 files at once
3. Upload all

**Expected Results**:
- ✓ All files upload
- ✓ Count shows "5 of 5 successful"
- ✓ Gallery shows all items

### Test 3: Media Viewing

**Steps**:
1. From Test 1, click media thumbnail
2. Should open in browser or download

**Expected Results**:
- ✓ Photos display inline
- ✓ Videos play with controls
- ✓ Download works

### Test 4: Client Access

**Steps**:
1. Log out as admin
2. Log in as client (CLI001 / passport)
3. Go to Client Portal
4. Click an order with media
5. Scroll to "Vehicle Media"

**Expected Results**:
- ✓ Media gallery appears
- ✓ All media visible
- ✓ Can view/download
- ✓ File names and sizes show

### Test 5: Security - Client Cannot Access Other Orders

**Steps**:
1. As client, try accessing different order's media
2. Try direct URL tampering: `/serve_media.php?id=999`

**Expected Results**:
- ✓ Access denied message
- ✓ 403 error
- ✓ No file served

### Test 6: Media Deletion

**Steps**:
1. As admin, upload a test file
2. Click delete (trash icon)
3. Confirm deletion

**Expected Results**:
- ✓ Confirmation modal
- ✓ File removed from gallery
- ✓ Physical file deleted
- ✓ Success message

### Test 7: Thumbnail Generation

**Steps**:
1. Upload a JPG photo
2. Check `/uploads/media/orders/thumbs/` directory

**Expected Results**:
- ✓ Thumbnail file created
- ✓ Thumbnail displays in gallery (200x200)
- ✓ Original image preserved

### Test 8: Video Streaming

**Steps**:
1. Upload an MP4 video
2. Click and play in browser
3. Try seeking/scrubbing timeline
4. Test pause/resume

**Expected Results**:
- ✓ Video plays smoothly
- ✓ Seeking works
- ✓ No errors in console
- ✓ Video metadata loads

### Test 9: Large File Upload

**Steps**:
1. Prepare a large video (80+ MB)
2. Upload via media modal

**Expected Results**:
- ✓ Accepts up to 100MB
- ✓ Progress shows
- ✓ Completes successfully

### Test 10: Invalid File Type

**Steps**:
1. Try uploading .exe, .zip, or .txt file
2. Try uploading .webp (should work)

**Expected Results**:
- ✓ Invalid files rejected
- ✓ Error message shown
- ✓ Valid files accept

### Test 11: Admin Badge Counts

**Steps**:
1. Upload 2 photos and 1 video
2. View Media Gallery header

**Expected Results**:
- ✓ Badge shows "3" (total)
- ✓ "2 photos" badge
- ✓ "1 video" badge

### Test 12: Media Descriptions (Future Feature)

**Steps**:
1. Note description field in upload modal
2. Verify descriptions display in gallery

**Current Status**: Not yet implemented, field added for future use

### Test 13: Cross-Browser Compatibility

**Test on**:
- Chrome (Desktop)
- Firefox (Desktop)
- Safari (if available)
- Edge (Desktop)
- Mobile browser

**Expected Results**:
- ✓ Upload works
- ✓ Gallery displays
- ✓ Video plays
- ✓ Downloads work

---

## Common Issues & Solutions

### Issue: "File size exceeds maximum allowed"

**Cause**: File larger than 100MB

**Solution**:
```php
// Edit config.php
define('MAX_MEDIA_FILE_SIZE', 209715200); // 200MB
```

### Issue: "File type not allowed" - Even for valid formats

**Cause**: MIME type detection failing

**Solution**:
1. Check file is actually the type (not renamed)
2. Test MIME detection:
   ```php
   echo mime_content_type('/path/to/file.jpg');
   ```
3. If GD not available, add manual MIME detection

### Issue: Thumbnail Not Generating

**Cause**: GD library not installed

**Solution**:
1. Install GD library (see Step 4 above)
2. Restart server
3. Re-upload image

**Fallback**: System works without thumbnails (displays generic icon)

### Issue: Media Not Visible to Client

**Cause 1**: Client not logged in

**Solution**: Verify client is authenticated

**Cause 2**: Media not linked to client's order

**Solution**: Verify order ownership:
```sql
SELECT c.id FROM clients c
JOIN users u ON c.user_id = u.id
WHERE u.id = ? AND c.id IN (SELECT client_id FROM orders WHERE id = ?);
```

**Cause 3**: Visibility set to 'admin' only

**Solution**: Check visibility setting:
```sql
SELECT visibility FROM order_media WHERE id = ?;
```

### Issue: "Access Denied" When Trying to Download

**Cause**: Not authorized for this media

**Solution**:
1. Verify logged in
2. Verify order ownership
3. Check database permissions

### Issue: High Disk Usage

**Solution**: Implement cleanup:
```php
// In admin panel or cron job
$result = cleanupOrphanedMedia();
echo "Cleaned up " . $result['count'] . " orphaned files";
```

### Issue: Slow Video Streaming

**Cause**: Large file size or slow network

**Solution**:
1. Add compression to videos before upload
2. Use MP4 format (best support)
3. Check server bandwidth
4. Verify range request support:
   ```php
   // Check in serve_media.php logs
   echo $_SERVER['HTTP_RANGE'] ?? 'No range request';
   ```

---

## Database Verification

### Verify Tables Created

```sql
-- Check media table
DESC order_media;

-- Check for index
SHOW INDEX FROM order_media;

-- Count media
SELECT COUNT(*) FROM order_media;

-- View recent uploads
SELECT * FROM order_media ORDER BY created_at DESC LIMIT 10;
```

### Verify Media Records

```sql
-- Media for specific order
SELECT * FROM order_media WHERE order_id = 1;

-- Count by type
SELECT media_type, COUNT(*) FROM order_media GROUP BY media_type;

-- Total storage
SELECT SUM(file_size) as total_size FROM order_media;
```

### Verify Access Logs

```sql
-- View recent accesses
SELECT * FROM media_logs ORDER BY accessed_at DESC LIMIT 20;

-- User access patterns
SELECT user_id, COUNT(*) as views FROM media_logs GROUP BY user_id;
```

---

## Performance Tuning

### Database Indexes

Already in place:
- `idx_order_id`: Fast lookup by order
- `idx_media_type`: Filter by photo/video
- `idx_created_at`: Sort by date

### Caching Strategy

- **Browser Cache**: 24 hours (set in serve_media.php)
- **Thumbnail Cache**: Permanent (until deleted)
- **Gallery Page Cache**: None (always fresh)

### Optimization Tips

1. **For Large Deployments**:
   ```php
   // Compress videos before upload
   // Use CDN for media delivery
   // Implement progressive JPEG for photos
   ```

2. **Database**:
   ```sql
   -- Analyze for query optimization
   ANALYZE TABLE order_media;
   
   -- Monitor slow queries
   SET GLOBAL slow_query_log = 'ON';
   ```

3. **Storage**:
   ```bash
   # Monitor disk usage
   du -sh /uploads/media
   
   # Find large files
   find /uploads/media -size +50M
   ```

---

## Backup & Recovery

### Backup Media Files

```bash
# Full backup
tar -czf media_backup.tar.gz /uploads/media/

# Incremental backup
rsync -av --delete /uploads/media/ /backup/media/
```

### Backup Database

```bash
# Backup order_media table
mysqldump -u root -p car_dealership order_media > order_media_backup.sql

# Full database backup
mysqldump -u root -p car_dealership > car_dealership_backup.sql
```

### Restore

```bash
# Restore files
tar -xzf media_backup.tar.gz

# Restore table
mysql -u root -p car_dealership < order_media_backup.sql
```

---

## Monitoring & Logging

### Enable Access Logging

Already enabled - all views logged to `media_logs` table

### Monitor Logs

```sql
-- Recent access
SELECT * FROM media_logs ORDER BY accessed_at DESC LIMIT 50;

-- Suspicious patterns
SELECT user_id, COUNT(*) as attempts 
FROM media_logs 
WHERE DATE(accessed_at) = CURDATE()
GROUP BY user_id HAVING attempts > 100;
```

### Clear Old Logs

```sql
-- Keep last 90 days
DELETE FROM media_logs 
WHERE accessed_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

---

## Administrative Commands

### Via Web Interface

- Upload: Click "Upload Media" in order details
- Delete: Click trash icon next to media
- View: Click media thumbnail

### Via Command Line / Database

```php
// Cleanup orphaned files
require 'functions_media.php';
$result = cleanupOrphanedMedia();
echo json_encode($result);

// Count media for order
$count = countOrderMedia(123);
echo "Order 123 has $count media files";

// Get storage used
$size = getTotalMediaSize(123);
echo formatBytes($size) . " used";
```

---

## Support & Troubleshooting

### Getting Help

1. **Check Logs**:
   ```bash
   tail -f /var/log/apache2/error.log
   ```

2. **Enable Debug Mode**:
   ```php
   // In config.php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

3. **Test Functions**:
   ```php
   echo mime_content_type('/path/to/file.jpg');
   echo extension_loaded('gd') ? 'GD Enabled' : 'GD Disabled';
   echo is_writable('/uploads/media/orders/') ? 'Writable' : 'Not Writable';
   ```

### Contact Development

Provide:
- Error message (full text)
- File involved (filename/size)
- User role (admin/client)
- Browser/OS
- Server logs

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2025-11-17 | Initial release |

---

**Setup Date**: November 17, 2025
**Last Updated**: November 17, 2025
