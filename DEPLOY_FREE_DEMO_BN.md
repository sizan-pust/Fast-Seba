# FastSheba Free Demo Deployment

Target:

- Laravel API + Admin + Seller: Render Free, Singapore
- Database: Aiven MySQL
- Product media: Cloudinary
- Customer website: Cloudflare Pages

## 1. Prepare

```powershell
cd "D:\Workspace\fastsheba-platform"

python .\prepare_fastsheba_demo_deployment.py --platform .
```

## 2. Import MySQL dump into Aiven

Wait until Aiven status is `Running`.

```powershell
cmd /c "mysql --host=mysql-3876fffe-sworkmail02-56a8.e.aivencloud.com --port=23354 --user=avnadmin -p --ssl-mode=REQUIRED --binary-mode=1 defaultdb < D:\Workspace\fastsheba-platform\fastsheba-demo.sql"
```

Verify:

```powershell
mysql `
  --host=mysql-3876fffe-sworkmail02-56a8.e.aivencloud.com `
  --port=23354 `
  --user=avnadmin `
  -p `
  --ssl-mode=REQUIRED `
  defaultdb
```

Inside MySQL:

```sql
SHOW TABLES;
SELECT COUNT(*) AS products FROM products;
SELECT COUNT(*) AS users FROM users;
SELECT COUNT(*) AS media_files FROM media;
EXIT;
```

## 3. Aiven CA base64

Download the CA certificate from Aiven.

```powershell
$CaPath = "D:\Workspace\fastsheba-platform\aiven-ca.pem"

[Convert]::ToBase64String(
    [IO.File]::ReadAllBytes($CaPath)
) | Set-Clipboard
```

Use the clipboard value as Render's `MYSQL_SSL_CA_BASE64`.

## 4. APP_KEY

```powershell
cd "D:\Workspace\fastsheba-platform\backend"

herd php artisan key:generate --show
```

## 5. Commit and push

```powershell
cd "D:\Workspace\fastsheba-platform"

git add `
  .gitignore `
  render.yaml `
  DEPLOY_FREE_DEMO_BN.md `
  deployment `
  backend `
  customer-web

git status
git commit -m "chore: prepare FastSheba free demo deployment"
git push -u origin deploy/free-demo
```

## 6. Render

1. Render → New → Blueprint
2. Connect the FastSheba repository
3. Branch: `deploy/free-demo`
4. Blueprint path: `render.yaml`
5. Enter every secret requested by the Blueprint
6. Deploy

Expected URL:

```text
https://fastsheba-demo-api-56a8.onrender.com
```

Check:

```text
/up
/admin
/seller
/api/settings
```

## 7. Cloudflare Pages

1. Cloudflare → Compute → Workers & Pages
2. Create application
3. Pages → Import an existing Git repository
4. Select the FastSheba repository
5. Production branch: `deploy/free-demo`
6. Root directory: `customer-web`
7. Framework preset: Next.js (Static HTML Export)
8. Build command: `npm run build`
9. Build output directory: `out`

Build variables:

```env
NEXT_PUBLIC_ADMIN_PANEL_URL=https://fastsheba-demo-api-56a8.onrender.com
NEXT_PUBLIC_SITE_URL=https://YOUR-PAGES-PROJECT.pages.dev
NEXT_PUBLIC_SSR=false
```

## 8. Final CORS

After the first Pages deployment, set Render:

```env
FRONTEND_URLS=https://YOUR-PAGES-PROJECT.pages.dev
```

Then redeploy Render.

## 9. Final URLs

```text
Customer: https://YOUR-PAGES-PROJECT.pages.dev
Backend:  https://fastsheba-demo-api-56a8.onrender.com
Admin:    https://fastsheba-demo-api-56a8.onrender.com/admin
Seller:   https://fastsheba-demo-api-56a8.onrender.com/seller
API:      https://fastsheba-demo-api-56a8.onrender.com/api
```
