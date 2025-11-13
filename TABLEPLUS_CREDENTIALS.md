# TablePlus Connection Settings - Quick Reference

## Your Server Database Credentials

Based on your server's `.env` file:

### Database Connection Details:
- **Host**: `127.0.0.1` (use SSH tunnel - database only accepts local connections)
- **Port**: `3306`
- **Database**: `laravel_db`
- **Username**: `laravel_user`
- **Password**: `StrongPassword123!`

### SSH Connection Details:
- **SSH Host**: `ubuntu-s-1vcpu-2gb-ams3-01` (or your server's IP address)
- **SSH Port**: `22`
- **SSH User**: `root`
- **SSH Password**: (your root password)

---

## TablePlus Setup Steps

### 1. Open TablePlus
- Click "Create a new connection" or press `Cmd + N`
- Select **MySQL**

### 2. Main Tab Configuration
```
Name: HighLevel Production
Host: 127.0.0.1
Port: 3306
User: laravel_user
Password: StrongPassword123!
Database: laravel_db
```

### 3. SSH Tab Configuration (REQUIRED)
Click on the **SSH** tab and configure:

```
✅ Enable SSH Tunnel: [CHECKED]
SSH Host: ubuntu-s-1vcpu-2gb-ams3-01
SSH Port: 22
SSH User: root
SSH Password: [your root password]
SSH Key: [optional - if you use SSH keys]
```

### 4. Test & Connect
- Click **Test** to verify connection
- Click **Connect**

---

## If SSH Hostname Doesn't Work

If `ubuntu-s-1vcpu-2gb-ams3-01` doesn't work, get your server's IP address:

```bash
# On your server, run:
hostname -I
# or
ip addr show | grep inet
```

Then use that IP address in the **SSH Host** field.

---

## Troubleshooting

### "Connection Refused"
- Make sure SSH Tunnel is enabled
- Verify SSH credentials are correct
- Check if you can SSH into the server from terminal

### "Authentication Failed"
- Double-check database username: `laravel_user`
- Double-check database password: `StrongPassword123!`
- Make sure you're using database credentials, not SSH credentials

### "SSH Tunnel Failed"
- Verify SSH host is correct (try IP address instead of hostname)
- Check SSH username is `root`
- Verify SSH password or key is correct
- Test SSH connection from terminal first: `ssh root@ubuntu-s-1vcpu-2gb-ams3-01`

---

## Quick Test from Terminal

Before using TablePlus, you can test the connection from your local machine:

```bash
# Create SSH tunnel (keep this terminal open)
ssh -L 3307:127.0.0.1:3306 root@ubuntu-s-1vcpu-2gb-ams3-01

# In another terminal, test MySQL connection
mysql -h127.0.0.1 -P3307 -ularavel_user -p'StrongPassword123!' laravel_db
```

If this works, TablePlus should work too!

