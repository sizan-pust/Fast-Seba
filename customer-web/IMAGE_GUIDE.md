# FastSheba Image Guide

## Source of truth

Product, store, variant, banner and prescription media are stored by the FastSheba Laravel backend through Spatie Media Library. Do not permanently hardcode product photos in Next.js.

## Dimensions and fit

| Media | Recommended size | Website fit |
|---|---:|---|
| Product main image | 1200×1200 | `contain` for packaged goods/medicine; `cover` for food |
| Product additional | 1200×1200 | same as main |
| Variant image | 1200×1200 | contain |
| Store logo | 800×800 | contain |
| Store banner | 1800×600 | cover |
| Home banner | 1636×960 | cover, exact 409:240 ratio |
| Prescription scan | high-resolution readable page | contain |

The website uses fixed aspect-ratio containers, centered object position and fallbacks. This prevents product grids and banner sliders from jumping when source images differ.

## Upload flow

- Seller panel: product/store/variant media upload endpoints already exist.
- Admin: banner management attaches images to `banner_image`.
- Prescription: customers can upload up to 5 JPG/JPEG/PNG/WebP/PDF files, 10 MB each.
- Public API returns absolute media URLs; verify Laravel `APP_URL` and `storage:link`.
