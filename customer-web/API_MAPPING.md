# Customer Web ↔ FastSheba API Mapping

| Website feature | Laravel API |
|---|---|
| Public settings | `GET /api/settings`, `GET /api/settings/{group}` |
| Categories/brands | `GET /api/categories`, `GET /api/brands` |
| Product search/detail | `GET /api/products/search`, `GET /api/products/{slug}` |
| Stores and map | `GET /api/stores`, `POST /api/stores/map`, `GET /api/stores/{slug}` |
| Delivery zones | `GET /api/delivery-zone`, `GET /api/delivery-zone/check` |
| Banners/sections | `GET /api/banners`, `GET /api/featured-sections` |
| Authentication | `/api/register`, `/api/login`, OTP/social routes |
| Cart/wishlist | `/api/user/cart/*`, `/api/user/wishlists/*` |
| Orders/returns | `/api/user/orders*` |
| Wallet | `/api/user/wallet`, `/api/user/wallet/transactions` |
| Notifications | `/api/user/notifications*` |
| Reviews/feedback | `/api/user/reviews`, `/api/user/feedback/*` |
| Prescriptions | `/api/user/prescriptions*` |
| Seller registration | `POST /api/seller/register` |

## Response compatibility

The frontend normalizes collection/paginator differences for banners, featured sections, FAQs and product reviews. Online gateways stay hidden unless enabled in backend payment settings. COD and wallet-backed checkout remain controlled by the Laravel payment configuration.
