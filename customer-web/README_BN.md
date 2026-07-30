# FastSheba Customer Website

এটি HyperLocal customer web source-এর FastSheba-branded implementation। UI structure, catalogue, cart, account, order, wallet, location এবং marketplace flows reference source-এর মতো রাখা হয়েছে; API layer FastSheba Laravel backend-এর সঙ্গে মানানসই করা হয়েছে।

## Included

- Green FastSheba branding and official logo
- Location-aware categories, products, stores and banners
- Browser GPS with Google Maps-ready address selection
- Pharmacy landing page and secure prescription upload
- Prescription status list/detail/cancellation
- Food Delivery and Marketplace landing pages
- Product/store/banner image fallbacks and fixed aspect ratios
- Existing cart, checkout, orders, addresses, wishlist, wallet and notifications
- SEO metadata, sitemap, robots and PWA branding

## Local run

```powershell
cd "D:\Workspace\fastsheba-platform\backend"
herd php artisan optimize:clear
herd php artisan db:seed --class="Database\Seeders\CustomerWebsiteSeeder" --force
herd php artisan serve --host=127.0.0.1 --port=8000
```

নতুন terminal:

```powershell
cd "D:\Workspace\fastsheba-platform\customer-web"
npm install
npm run dev
```

Open:

```text
Customer website: http://127.0.0.1:3000
Laravel API:      http://127.0.0.1:8000/api
Admin panel:      http://127.0.0.1:8000/admin
Seller panel:     http://127.0.0.1:8000/seller/login
```

## Google Maps

`customer-web/.env`:

```dotenv
NEXT_PUBLIC_GOOGLE_MAPS_API_KEY=YOUR_BROWSER_KEY
```

`backend/.env`:

```dotenv
GOOGLE_MAPS_API_KEY=YOUR_BROWSER_KEY
FRONTEND_URLS=http://127.0.0.1:3000,http://localhost:3000,https://fastsheba.com.bd,https://www.fastsheba.com.bd
```

Recommended Google APIs:

- Maps JavaScript API
- Places API (New)
- Geocoding API

Browser key-তে HTTP referrer restriction ব্যবহার করবে। Production GPS/geolocation-এর জন্য HTTPS প্রয়োজন।

## Product image system

নতুন third-party product-image API দরকার নেই। Existing Laravel + Spatie Media Library-ই source of truth:

1. Seller/Admin product media upload করে।
2. File public media disk-এ থাকে।
3. API `main_image`, `additional_images`, variant `image`, store logo/banner এবং `image_fit` পাঠায়।
4. Next.js website একই URL render করে।
5. Missing image হলে FastSheba placeholder দেখায়।

Recommended source dimensions:

- Product main/additional: `1200 × 1200`, JPG/WebP, maximum 5 MB
- Medicine box/bottle: `image_fit = contain`
- Food/lifestyle photography: `image_fit = cover`
- Store logo: `800 × 800`
- Store banner: `1800 × 600`
- Home banner: `1636 × 960` (`409:240`)
- Prescription: readable JPG/PNG/WebP/PDF, maximum 10 MB each, maximum 5 files

`APP_URL` ঠিক রাখতে হবে এবং `public/storage` link existing থাকতে হবে।

## Production environment

```dotenv
NEXT_PUBLIC_API_BASE_URL=https://api.example.com/api
NEXT_PUBLIC_ADMIN_PANEL_URL=https://api.example.com
NEXT_PUBLIC_SITE_URL=https://fastsheba.com.bd
NEXT_PUBLIC_GOOGLE_MAPS_API_KEY=...
NEXT_PUBLIC_SSR=false
```

Laravel:

```dotenv
APP_URL=https://api.example.com
FRONTEND_URLS=https://fastsheba.com.bd,https://www.fastsheba.com.bd
GOOGLE_MAPS_API_KEY=...
```

## Verification

```powershell
cd "D:\Workspace\fastsheba-platform\backend"
herd php artisan test tests/Feature/Api/CustomerWebsiteApiTest.php
herd php artisan test

cd "..\customer-web"
npm run lint
npm run build
```
