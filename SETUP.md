# DONATE+ — How to run this project on another laptop

This guide is for setting up the project on a second computer (e.g. Jayash's
laptop) so it runs exactly like it does on the original machine.

Everything runs **locally** with XAMPP — there's no server to pay for.

---

## What you need

- **XAMPP** (gives you Apache + PHP + MySQL/MariaDB + phpMyAdmin).
  Download: https://www.apachefriends.org

---

## Step 1 — Install XAMPP

Install XAMPP with default settings. After it's installed, open the **XAMPP
Control Panel** and click **Start** on both **Apache** and **MySQL**.

---

## Step 2 — Get the project files into htdocs

The whole website lives in one folder called `donation`. Put a copy of that
folder inside XAMPP's web root:

```
C:\xampp\htdocs\donation
```

Two ways to get the files:

**Option A — Git (recommended, since the project already uses Git)**
If you push the project to GitHub, the other person can clone it:
```
cd C:\xampp\htdocs
git clone <your-github-repo-url> donation
```

**Option B — Copy the folder manually**
Zip the `donation` folder on the first machine, send it over (USB / Drive /
WhatsApp), and unzip it into `C:\xampp\htdocs\` so the path is
`C:\xampp\htdocs\donation`.

> Note: don't copy the `uploads/` images unless you also copy the database —
> they only make sense together.

---

## Step 3 — Set up the database

Open **http://localhost/phpmyadmin** in the browser.

**The easiest way (keeps all data + the two admin accounts):**
1. On the **original** machine: phpMyAdmin → click the `donation_db` database →
   **Export** tab → **Go**. This downloads a `donation_db.sql` file.
2. On the **new** machine: phpMyAdmin → **New** → create a database named
   exactly `donation_db` → open it → **Import** tab → choose that
   `donation_db.sql` file → **Go**.

Now the second machine has the same users, donations, and admin logins.

**Or, for a fresh empty database (no data yet):**
1. phpMyAdmin → **New** → create a database named `donation_db`.
2. Open it → **SQL** tab → paste the full contents of `schema.sql` (from the
   project folder) → **Go**. This creates all the tables and the default
   categories.
3. You'll then need to create the two admin accounts (see Step 5).

---

## Step 4 — Check the database connection

Open `config/database.php`. On a normal XAMPP install these defaults already
work — no change needed:

```php
$host = "localhost";
$username = "root";
$password = "";          // XAMPP's MySQL root has no password by default
$database = "donation_db";
```

If the other machine set a MySQL password during install, put it in `$password`.

---

## Step 5 — (Only if you used a fresh database) create the admins

If you imported the `.sql` dump in Step 3, **skip this** — the admins came with
it.

If you started fresh, you need the one-time `create-admin.php` script to make the
two admin accounts, then delete it. Ask for that file (it was removed after the
first setup for security), or add the admins directly in phpMyAdmin.

---

## Step 6 — Open the website

In the browser go to:

```
http://localhost/donation/index.html
```

- Log in at `http://localhost/donation/login.html`.
- Admin login (if using the shared database): `adminAnshu@donate.local` /
  the password you set.

That's it — it now runs on the second laptop exactly like the first.

---

## Bonus — letting your friend open it over the same Wi-Fi (optional)

If you'd rather your friend open the site running on **your** laptop (instead of
installing their own copy), and you're both on the same Wi-Fi:

1. On your machine, find your local IP: open Command Prompt → type `ipconfig` →
   note the **IPv4 Address** (looks like `192.168.x.x`).
2. Your friend opens `http://192.168.x.x/donation/` in their browser.
3. This needs XAMPP/Apache to allow network access and your firewall to permit
   it — it can be fiddly, so for coursework the "each runs their own copy"
   method above is usually simpler and more reliable.
