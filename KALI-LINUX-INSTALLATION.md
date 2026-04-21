# 🐉 BankLab — Kali Linux Installation Guide

> **For students setting up BankLab on Kali Linux using the native Apache2 + MariaDB stack.**  
> No XAMPP required — Kali Linux already has everything pre-installed.

---

## ✅ Prerequisites

Kali Linux already ships with:
- **Apache2** — Web server
- **MariaDB** — Database server (MySQL-compatible)
- **PHP** — Server-side scripting

No additional software installation needed!

---

## ⚙️ Step-by-Step Setup

### Step 1 — Clone the Repository

```bash
cd ~
git clone https://github.com/mr-bala-kavi/bank-lab.git
cd bank-lab
```

---

### Step 2 — Start Apache and MariaDB Services

```bash
systemctl start apache2
systemctl start mysql
```

Verify both are running:

```bash
systemctl status apache2
systemctl status mysql
```

You should see `active (running)` for both.

---

### Step 3 — Link the Project to the Web Root

Apache serves files from `/var/www/html/`. Create a symlink so Apache can find your project:

```bash
ln -sf /home/kali/bank-lab /var/www/html/bank-lab
```

---

### Step 4 — Set File Permissions

Allow Apache (which runs as `www-data`) to read your project files:

```bash
# Give execute permission on your home directory so Apache can traverse it
chmod o+x /home/kali

# Give read/execute permission on all project files
chmod -R 755 /home/kali/bank-lab

# Give full write permission to upload and export folders (needed for RCE lab)
chmod -R 777 /home/kali/bank-lab/uploads
chmod -R 777 /home/kali/bank-lab/exports
```

---

### Step 5 — Import the Database

Create the `bank_lab` database and seed all test data:

```bash
sudo mysql -u root < /home/kali/bank-lab/setup.sql
```

> ⚠️ If you see `ERROR 1062: Duplicate entry` — that's fine!  
> It just means the database already existed. The tables and data are intact.

---

### Step 6 — Fix MariaDB Authentication for PHP

By default, Kali's MariaDB root user uses `unix_socket` authentication (only works with `sudo`).  
PHP can't use `sudo`, so we switch root to use a regular password (empty password, matching `db.php`):

```bash
sudo mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY ''; FLUSH PRIVILEGES;"
```

> 💡 **Why this step?**  
> Your `db.php` connects as `root` with no password. MariaDB's default auth on Kali blocks this.  
> This command allows PHP to connect directly.

---

### Step 7 — Access BankLab in Browser

Open your browser and go to:

```
http://localhost/bank-lab/login.php
```

🎉 **BankLab is now live!**

---

## 👤 Test Accounts

| Username | Password     | Role         | Balance      |
|----------|--------------|--------------|--------------|
| `alice`  | `password123`| Regular User | $12,350      |
| `bob`    | `qwerty999`  | Regular User | $4,820       |
| `carol`  | `carol2024`  | Regular User | $31,350      |
| `dave`   | `dave1234`   | Regular User | $9,600       |
| `test`   | `Test@123`   | Regular User | $1,000,000   |
| `admin`  | `Admin@123`  | System Admin | —            |

---

## 🔄 Start the Lab Again (Next Session)

Every time you reboot Kali, run these two commands to start the services again:

```bash
systemctl start apache2
systemctl start mysql
```

Then open `http://localhost/bank-lab/login.php` — everything else is persistent.

---

## 🛠️ Troubleshooting

| Problem | Fix |
|---------|-----|
| `403 Forbidden` in browser | Run `chmod o+x /home/kali` |
| `Connection failed` PHP error | Run Step 6 again (MariaDB auth fix) |
| Blank page / no output | Check `sudo journalctl -u apache2` for errors |
| `Duplicate entry` on SQL import | Safe to ignore — data already exists |
| Symlink not working | Re-run `ln -sf /home/kali/bank-lab /var/www/html/bank-lab` |

---

## 🗺️ Key URLs

| Page         | URL                                        |
|--------------|--------------------------------------------|
| Login        | http://localhost/bank-lab/login.php        |
| Dashboard    | http://localhost/bank-lab/dashboard.php    |
| Admin Panel  | http://localhost/bank-lab/admin.php        |
| API          | http://localhost/bank-lab/api.php          |
| Upload       | http://localhost/bank-lab/upload.php       |
| Export       | http://localhost/bank-lab/export.php       |
| Webhook      | http://localhost/bank-lab/webhook.php      |

---

## 💡 Difference: Windows (XAMPP) vs Kali Linux

| Feature        | Windows XAMPP              | Kali Linux (Native)              |
|----------------|----------------------------|----------------------------------|
| Web root       | `C:\xampp\htdocs\`         | `/var/www/html/` (via symlink)   |
| Start services | XAMPP Control Panel        | `systemctl start apache2 mysql`  |
| DB engine      | MySQL                      | MariaDB (MySQL-compatible)       |
| PHP config     | `C:\xampp\php\php.ini`     | `/etc/php/*/apache2/php.ini`     |
| phpMyAdmin     | `http://localhost/phpmyadmin` | `http://localhost/phpmyadmin` |

---

*Happy Hacking! 🐉 — BankLab by [@mr-bala-kavi](https://github.com/mr-bala-kavi)*
