# TablePlus Setup Guide - Connect to Remote Database

## Step 1: Get Database Credentials from Server

SSH into your server and check the `.env` file:

```bash
# SSH into your server (replace with your actual server details)
ssh user@your-server-ip

# Navigate to your Laravel project directory
cd /path/to/your/laravel/project

# View the .env file (or use cat/nano/vim)
cat .env | grep DB_
```

You need to find these values:
- `DB_HOST` - Database host (could be `127.0.0.1`, `localhost`, or an IP address)
- `DB_PORT` - Usually `3306` for MySQL
- `DB_DATABASE` - Database name
- `DB_USERNAME` - Database username
- `DB_PASSWORD` - Database password

**Example output:**
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=highlevel
DB_USERNAME=highlevel_user
DB_PASSWORD=your_secure_password
```

## Step 2: Configure TablePlus

### Option A: Using SSH Tunnel (Recommended - Most Secure)

This is the safest method as it doesn't expose your database port publicly.

1. **Open TablePlus**
2. **Click "Create a new connection"** or press `Cmd + N`
3. **Select "MySQL"**
4. **Configure the connection:**

   **Main Tab:**
   - **Name**: `HighLevel Production (SSH)`
   - **Host**: `127.0.0.1` (localhost, because we're using SSH tunnel)
   - **Port**: `3306` (or the port from your .env)
   - **User**: `[DB_USERNAME from .env]`
   - **Password**: `[DB_PASSWORD from .env]`
   - **Database**: `[DB_DATABASE from .env]`

   **SSH Tab (Click on it):**
   - ✅ **Enable SSH Tunnel**: Check this box
   - **SSH Host**: `your-server-ip` (your server's IP address or domain)
   - **SSH Port**: `22` (default SSH port)
   - **SSH User**: `your-ssh-username` (the username you use to SSH into the server)
   - **SSH Password**: `your-ssh-password` (or use SSH key)
   - **SSH Key**: (Optional) If you use SSH keys, browse and select your private key file

5. **Click "Test"** to verify the connection
6. **Click "Connect"**

### Option B: Direct Connection (If Remote Access is Enabled)

⚠️ **Note**: This only works if your server's database allows remote connections and firewall rules permit it.

1. **Open TablePlus**
2. **Click "Create a new connection"** or press `Cmd + N`
3. **Select "MySQL"**
4. **Configure the connection:**

   **Main Tab:**
   - **Name**: `HighLevel Production (Direct)`
   - **Host**: `[DB_HOST from .env]` or `your-server-ip`
   - **Port**: `[DB_PORT from .env]` (usually `3306`)
   - **User**: `[DB_USERNAME from .env]`
   - **Password**: `[DB_PASSWORD from .env]`
   - **Database**: `[DB_DATABASE from .env]`

5. **Click "Test"** to verify the connection
6. **Click "Connect"**

## Step 3: View Tables

Once connected, you'll see:
- All databases in the left sidebar
- Expand your database to see all tables
- Click on any table to view its data
- Right-click on a table for options (Structure, Data, etc.)

## Common Issues & Solutions

### Issue: "Connection Refused"
**Solution**: 
- Make sure you're using SSH Tunnel if the database only allows local connections
- Verify the SSH connection details are correct
- Check if the database is running on the server

### Issue: "Authentication Failed"
**Solution**:
- Double-check the username and password from `.env`
- Make sure you're using the database username, not the SSH username
- Verify the database user has proper permissions

### Issue: "SSH Tunnel Failed"
**Solution**:
- Verify SSH credentials (username, password, or key)
- Check if SSH port 22 is accessible
- Make sure you can SSH into the server from terminal first
- Try using SSH key instead of password

### Issue: "Can't Connect to Database"
**Solution**:
- If using direct connection, check if the database allows remote connections
- Verify firewall rules allow connections from your IP
- Check if `DB_HOST` in `.env` is `127.0.0.1` (only allows local connections - use SSH tunnel)

## Quick Reference

**To get credentials:**
```bash
ssh user@server-ip
cd /path/to/project
cat .env | grep DB_
```

**TablePlus Settings (SSH Tunnel):**
- Host: `127.0.0.1`
- Port: `3306`
- User: From `.env` `DB_USERNAME`
- Password: From `.env` `DB_PASSWORD`
- Database: From `.env` `DB_DATABASE`
- SSH Host: Your server IP
- SSH User: Your SSH username

## Security Best Practices

1. ✅ **Always use SSH Tunnel** for production databases
2. ✅ **Never share** your database credentials
3. ✅ **Use strong passwords** for database users
4. ✅ **Limit remote access** - only allow SSH tunnel connections
5. ✅ **Keep SSH keys secure** - don't commit them to version control

