# Deployment Commands

After pulling the latest changes on the server, run these commands:

## 1. Pull Latest Changes
```bash
git pull origin main
```

## 2. Run Database Migration
```bash
php artisan migrate
```
This will add the `tap_merchant_id` column to the `users` table.

## 3. Clear Application Cache (Optional but Recommended)
```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

## 4. Optimize Application (Production Only)
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Summary of Changes Applied
- ✅ Added `tap_merchant_id` field to users table
- ✅ Updated welcome.blade.php to include Merchant ID field
- ✅ Updated connectOrDisconnect to save merchant_id
- ✅ Updated createTapCharge to use merchant_id from database
- ✅ Added secretKey logging in createTapCharge

