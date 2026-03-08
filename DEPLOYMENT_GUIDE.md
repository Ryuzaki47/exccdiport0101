# CCDI Account Portal — Vercel Deployment Checklist

> **Last Updated:** March 8, 2026  
> **Status:** Ready for Production Deployment  
> **Platform:** Vercel + Railway MySQL + Namecheap Domain

---

## ✅ PRE-DEPLOYMENT CHECKLIST

### Step 1: Prepare Local Environment
- [ ] Run `composer install`
- [ ] Run `npm install`
- [ ] Run `npm run build` (test build locally)
- [ ] Verify `.env` file exists locally
- [ ] Run `php artisan migrate` on local SQLite
- [ ] Test the application locally at `http://localhost:8000`
- [ ] Commit all changes to Git: `git push`

### Step 2: Get Your APP_KEY
```bash
# Run this locally to get the base64 APP_KEY
php artisan key:generate --show
```
**Copy the entire output** (including `base64:` prefix). You'll need this for Vercel.

### Step 3: Set Up Railway MySQL Database
```
1. Visit https://railway.app
2. Click "New Project" → "Deploy from Template" → Search "MySQL"
3. Connect your GitHub account
4. Railway creates a MySQL instance
5. Go to Dashboard → Your Project → Variables
6. Copy these environment variables:
   - DATABASE_URL (or individual: HOST, PORT, DATABASE, USER, PASSWORD)
```

**Record these credentials:**
```
DB_HOST: ___________________
DB_PORT: ___________________
DB_DATABASE: ___________________
DB_USERNAME: ___________________
DB_PASSWORD: ___________________
```

---

## 🚀 DEPLOYMENT STEPS

### Step 4: Connect Vercel to GitHub

```bash
# Install Vercel CLI
npm install -g vercel

# Login to Vercel
vercel login
```

### Step 5: First Vercel Deployment

```bash
# From your project root
cd C:\laragon\www\exccdiport0101

# Deploy to preview
vercel

# When prompted:
# - Link to existing project? No (first time)
# - Set project name? exccdiport0101
# - Override settings? Yes
# - Build Command? (Use default from vercel.json)
# - Output Directory? public
# - Root directory? ./
```

**Vercel gives you a preview URL** like: `https://exccdiport0101-git-main-yourname.vercel.app`

### Step 6: Add Environment Variables to Vercel

Go to **Vercel Dashboard → Your Project → Settings → Environment Variables**

Add these variables:

```
APP_NAME=CCDIAccPortal
APP_ENV=production
APP_KEY=base64:YOUR_KEY_FROM_STEP_2
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=your-railway-host
DB_PORT=3306
DB_DATABASE=your-railway-db
DB_USERNAME=your-railway-user
DB_PASSWORD=your-railway-password

SESSION_DRIVER=cookie
CACHE_STORE=array
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
```

### Step 7: Deploy to Production

```bash
# Deploy to production
vercel --prod
```

Vercel will rebuild with the new environment variables and give you the production URL.

---

## 🌐 DOMAIN SETUP (Namecheap → Vercel)

### Step 8: Add Domain to Vercel

1. Go to **Vercel Dashboard → Your Project → Settings → Domains**
2. Click **"Add Domain"**
3. Enter: `yourdomain.com`
4. Click **Add**
5. Vercel shows DNS instructions

### Step 9: Configure DNS in Namecheap

1. Login to **Namecheap → Domain List → Manage** (your domain) **→ Advanced DNS**
2. **Delete** existing records for `@` (except MX if you use email)
3. **Add/Update** these records:

| Type | Host | Value | TTL |
|---|---|---|---|
| A Record | @ | `76.76.21.21` | Automatic |
| CNAME Record | www | `cname.vercel-dns.com.` | Automatic |

> Note the trailing dot `.` on the CNAME value — it's required

4. Click **Save**
5. **Wait 5-30 minutes** for DNS propagation

### Step 10: Verify DNS Propagation

```bash
# Check if DNS is ready
nslookup yourdomain.com

# Should show Vercel IP (76.76.21.21)
```

Or use: https://dnschecker.org (enter your domain, check "A Record")

---

## 🗄️ DATABASE MIGRATION

### Step 11: Run Migrations on Production

Once deployment succeeds and domain is set up:

```bash
# Option A: SSH into Vercel (if available)
vercel env pull .env.production

# Option B: Add to Vercel build command (recommended)
# Edit vercel.json buildCommand to include:
php artisan migrate --force
```

Or manually via Railway dashboard:

1. Go to Railway → Your Project → MySQL
2. Click **"Connect"** → **"CLI"**
3. Use provided connection string to run:
   ```bash
   mysql> /* Use the connection string provided */
   mysql> /* Then verify tables exist */
   ```

---

## ✨ VERIFICATION CHECKLIST

### Step 12: Test Everything Works

| Test | How to Verify | Status |
|---|---|---|
| **Domain resolves** | Visit `https://yourdomain.com` | ☐ |
| **SSL certificate** | Check for 🔒 padlock | ☐ |
| **App loads** | Dashboard page appears | ☐ |
| **Database connects** | Try to register a student | ☐ |
| **Sessions work** | Login and refresh page — stay logged in | ☐ |
| **Database has tables** | Check in Railway dashboard → Data | ☐ |
| **No 500 errors** | Check Vercel logs for errors | ☐ |
| **Assets load** | CSS/JS styling shows (not plain HTML) | ☐ |

**Check Vercel Logs:**
```bash
# View real-time logs
vercel logs yourdomain.com --follow

# View specific deployment logs
vercel logs yourdomain.com
```

---

## ⚠️ IMPORTANT WARNINGS

### File Uploads
❌ **Vercel filesystem is ephemeral** — uploaded files will be deleted after deployment!

✅ **Fix:** Integrate file storage:
- **Option 1:** AWS S3
- **Option 2:** Cloudinary (easiest)
- **Option 3:** Google Cloud Storage

### Background Jobs
❌ **QUEUE_CONNECTION=sync** means jobs run immediately (no queue)

✅ **Fix (later):** Use Railway Cron or external job service

### Database Backups
✅ **Railway auto-backs up** — no action needed
🔄 **But download backups monthly** just in case

---

## 🔧 TROUBLESHOOTING

### Problem: "502 Bad Gateway"
```
Cause: Laravel bootstrap error or database connection failed
Fix:
1. Check vercel.json buildCommand syntax
2. Verify DATABASE_URL in Vercel env vars
3. Run: vercel logs yourdomain.com
```

### Problem: "Database connection failed"
```
Cause: Railway credentials wrong or network blocked
Fix:
1. Copy correct credentials from Railway dashboard
2. Test locally: mysql -h HOST -u USER -p PASSWORD -D DATABASE
3. Ensure Railway IP is whitelisted (usually automatic)
```

### Problem: "Session lost after refresh"
```
Cause: Using file sessions on ephemeral filesystem
Fix:
Ensure SESSION_DRIVER=cookie in .env (already configured)
```

### Problem: "Assets (CSS/JS) not loading"
```
Cause: Build didn't run or asset paths are wrong
Fix:
1. Run npm run build locally to test
2. Check Vite config paths match public/build
3. Verify vercel.json routes include /build/
```

---

## 📋 POST-DEPLOYMENT

### Monitoring
- [ ] Set up error monitoring: **Sentry** or **Rollbar**
- [ ] Monitor uptime: **Uptime Robot** (free)
- [ ] Track performance: **Vercel Analytics**

### Maintenance
- [ ] Weekly: Check Vercel logs for errors
- [ ] Monthly: Download Railway database backup
- [ ] Quarterly: Update PHP/Laravel/dependencies

### Security
- [ ] Enable **2FA** on Vercel account
- [ ] Enable **2FA** on Railway account
- [ ] Enable **2FA** on Namecheap account
- [ ] Set up **SSL certificate** auto-renewal (Vercel does this automatically)
- [ ] Review Laravel `.env` — ensure `APP_DEBUG=false` on production

---

## 🎯 QUICK REFERENCE COMMANDS

```bash
# View current environment variables
vercel env ls

# Pull production env vars to local .env.production
vercel env pull

# View live logs
vercel logs yourdomain.com --follow

# Redeploy without code changes
vercel --prod

# Remove deployment
vercel remove
```

---

## 📞 SUPPORT

If deployment fails:

1. **Check Vercel logs:** `vercel logs yourdomain.com`
2. **Check Railway logs:** Railway Dashboard → Your Project → Logs
3. **Test Laravel locally:** `php artisan serve` with production .env
4. **Verify DNS:** https://dnschecker.org

---

**Deployment Date:** ___________  
**Deployed By:** ___________  
**Production URL:** https://yourdomain.com  
**Status:** ☐ Live
