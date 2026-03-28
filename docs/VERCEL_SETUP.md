# Vercel Deployment Configuration

## Changes Made

1. **Upgraded PHP Runtime**: `vercel-php@0.7.2` → `vercel-php@0.8.29`
   - Newer version includes MySQL PDO extension pre-compiled
   - Fixes "could not find driver" error

2. **Disabled Telescope by Default**: 
   - Telescope only enabled in `local` environment
   - Prevents database access during deployment

3. **Updated `.vercelignore`**:
   - Prevents `.env` file from being deployed
   - Vercel uses environment variables instead

## Required Vercel Environment Variables

You MUST set these in **Vercel Dashboard** → **Project Settings** → **Environment Variables**:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<your base64 key from local .env>
DB_CONNECTION=mysql
DB_HOST=<your Railway host or DB host>
DB_PORT=<your DB port>
DB_DATABASE=<your database name>
DB_USERNAME=<your DB username>
DB_PASSWORD=<your DB password>
TELESCOPE_ENABLED=false
```

## Important Notes

- **Never deploy `.env` to Git** — Use environment variables instead
- **Database must be accessible** from Vercel (Railway, AWS RDS, etc.)
- **Storage permissions** — Make sure `/storage` directory is writable
- **Migrations** — Run migrations locally, commit to database (Vercel can't run `php artisan migrate`)

## Deployment Steps

1. Commit your changes:
```bash
git add vercel.json config/telescope.php api/php.ini .vercelignore .env.example
git commit -m "Upgrade Vercel PHP runtime and disable Telescope in production"
git push
```

2. Set environment variables in Vercel Dashboard

3. Trigger a deployment:
   - Via `git push` (if connected)
   - Or via **Vercel Dashboard** → **Deployments** → **Redeploy**

4. Check deployment logs for errors

## Verify Deployment

Once deployed, check:
```
GET https://your-domain.vercel.app/
```

Should load successfully without database errors.
