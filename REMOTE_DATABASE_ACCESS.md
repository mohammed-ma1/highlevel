# How to Access Remote Database on Server

This guide shows you how to connect to and view the database that's running on your production server.

## Prerequisites

You'll need the following information from your server's `.env` file:
- `DB_HOST` - Database host (IP address or domain)
- `DB_PORT` - Database port (usually 3306 for MySQL)
- `DB_DATABASE` - Database name
- `DB_USERNAME` - Database username
- `DB_PASSWORD` - Database password

## Method 1: SSH Tunnel (Recommended - Most Secure)

This method creates a secure tunnel through SSH to access the database.

### Step 1: Create SSH Tunnel

```bash
ssh -L 3307:localhost:3306 user@your-server-ip
```

Or if your database is on a different host:
```bash
ssh -L 3307:database-host:3306 user@your-server-ip
```

**Explanation:**
- `-L 3307:localhost:3306` creates a local port forward
- `3307` is the local port (use any available port)
- `localhost:3306` is the database on the remote server
- Keep this SSH session open while accessing the database

### Step 2: Connect Using MySQL Client

In a new terminal window:

```bash
mysql -h127.0.0.1 -P3307 -uDB_USERNAME -pDB_DATABASE
```

Or using Laravel Artisan:
```bash
# Temporarily update your local .env to use the tunnel
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

php artisan db:show
php artisan db:table users
```

## Method 2: Direct Connection (If Remote Access is Enabled)

If your server's database allows remote connections:

### Using MySQL Command Line

```bash
mysql -hYOUR_SERVER_IP -P3306 -uDB_USERNAME -p DB_DATABASE
```

You'll be prompted for the password.

### Using Laravel Artisan

Update your local `.env` file temporarily:

```env
DB_CONNECTION=mysql
DB_HOST=YOUR_SERVER_IP
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Then run:
```bash
php artisan config:clear
php artisan db:show
php artisan db:table users
```

## Method 3: Using MySQL GUI Tools

### MySQL Workbench / TablePlus / DBeaver / phpMyAdmin

1. **Via SSH Tunnel:**
   - Host: `127.0.0.1`
   - Port: `3307` (or your tunnel port)
   - Username: Your database username
   - Password: Your database password
   - SSH Tunnel: Enable
     - SSH Host: `your-server-ip`
     - SSH Username: Your SSH username
     - SSH Port: `22`

2. **Direct Connection (if allowed):**
   - Host: `YOUR_SERVER_IP`
   - Port: `3306`
   - Username: Your database username
   - Password: Your database password

## Method 4: SSH into Server and Access Database Directly

### Step 1: SSH into Server

```bash
ssh user@your-server-ip
```

### Step 2: Navigate to Project Directory

```bash
cd /path/to/your/laravel/project
```

### Step 3: Use Laravel Artisan Commands

```bash
php artisan db:show
php artisan db:table users
```

### Step 4: Or Use MySQL Command Line on Server

```bash
# If MySQL is installed on the server
mysql -uDB_USERNAME -p DB_DATABASE

# Or if using Docker
docker exec -it container_name mysql -uDB_USERNAME -p DB_DATABASE
```

## Method 5: Using Laravel Tinker on Server

SSH into your server and run:

```bash
cd /path/to/your/laravel/project
php artisan tinker
```

Then in tinker:
```php
// Show all tables
DB::select('SHOW TABLES');

// Get all users
DB::table('users')->get();

// Get specific user
DB::table('users')->where('id', 1)->first();

// Count records
DB::table('users')->count();
```

## Common SQL Queries

Once connected, you can run:

```sql
-- Show all tables
SHOW TABLES;

-- Describe table structure
DESCRIBE users;

-- View all data from a table
SELECT * FROM users;

-- View specific columns
SELECT id, name, email FROM users;

-- Count records
SELECT COUNT(*) FROM users;

-- View with limit
SELECT * FROM users LIMIT 10;
```

## Security Notes

⚠️ **Important Security Considerations:**

1. **SSH Tunnel is Recommended**: Always use SSH tunnel for production databases to avoid exposing database ports publicly.

2. **Firewall Rules**: Make sure your server firewall only allows database access from trusted IPs or through SSH.

3. **Never Commit Credentials**: Never commit `.env` files with production credentials to version control.

4. **Use Strong Passwords**: Ensure your database has strong passwords.

5. **Limit Remote Access**: If possible, disable direct remote database access and only allow SSH tunnel connections.

## Troubleshooting

### Connection Refused
- Check if database is running on the server
- Verify firewall rules allow connections
- Check if database is bound to `127.0.0.1` (localhost only) or allows remote connections

### Authentication Failed
- Verify username and password are correct
- Check if user has permissions to access the database
- Ensure user is allowed to connect from your IP

### SSH Tunnel Issues
- Make sure SSH connection is active
- Verify the port forwarding syntax
- Check if local port is already in use

## Quick Reference

**Local Database (Docker):**
```bash
docker exec -it highlevel_mysql mysql -uhighlevel -phighlevel highlevel
```

**Remote Database (SSH Tunnel):**
```bash
# Terminal 1: Create tunnel
ssh -L 3307:localhost:3306 user@server-ip

# Terminal 2: Connect
mysql -h127.0.0.1 -P3307 -uusername -p database
```

**Remote Database (Direct):**
```bash
mysql -hserver-ip -P3306 -uusername -p database
```

