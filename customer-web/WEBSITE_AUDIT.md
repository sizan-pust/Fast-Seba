# FastSheba Website Audit

## Reference web source

The uploaded HyperLocal web is a Next.js/React Pages Router customer storefront with:

- Home banners and featured sections
- Location-aware catalogue
- Categories, brands, stores and products
- Cart, checkout, orders and returns
- Account, addresses, wishlist, wallet and notifications
- Policies, FAQs and seller registration

The FastSheba implementation keeps this visual and functional foundation instead of replacing it with a different template.

## FastSheba additions

- Official FastSheba green brand assets
- Pharmacy-first home/service entry
- Prescription camera/file upload
- Prescription list, details and cancellation
- Pharmacy, Food Delivery, Marketplace, Contact and Blog routes
- Bangladesh defaults: Dhaka coordinates, BDT/৳ and BD address country
- GPS fallback when Google Maps key is absent
- Google Maps/Places/Geocoding integration point when a key is supplied
- API compatibility normalizers for banners, featured sections, FAQs and reviews
- Local fallback banners so the layout remains complete before admin uploads

## Laravel integration changes

- Public settings groups now include `web`, `payment`, `home_general_settings` and `advertisement`
- Website defaults and FastSheba banners are seeded without overwriting admin changes
- Featured sections return full product card payloads
- Banner payload contains image and related slugs expected by the web
- Delivery zones can be opened by ID or slug
- Public seller reviews endpoint added
- CORS allows configured customer website origins
- Customer website feature tests added

## Known deployment dependency

Google address autocomplete and draggable map require a valid browser Google Maps key. Without it, browser GPS and coordinates still work and delivery-zone checking remains available.
