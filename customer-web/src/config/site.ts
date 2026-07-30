export type SiteConfig = typeof siteConfig;

export const siteConfig = {
  name: "FastSheba",
  description:
    "Food, grocery, pharmacy and marketplace services from nearby trusted sellers.",
  metaKeywords:
    "FastSheba, pharmacy delivery Bangladesh, upload prescription, grocery delivery, food delivery, marketplace, nearby stores",
  metaDescription:
    "FastSheba is a Bangladesh multi-vendor marketplace for pharmacy, food, grocery and daily essentials.",
  navItems: [
    { label: "Home", href: "/" },
    { label: "Pharmacy", href: "/pharmacy" },
    { label: "Food Delivery", href: "/food-delivery" },
    { label: "Marketplace", href: "/marketplace" },
    { label: "Upload Prescription", href: "/prescriptions" },
  ],
  navMenuItems: [
    { label: "Profile", href: "/my-account" },
    { label: "Orders", href: "/my-account/orders" },
    { label: "Prescriptions", href: "/my-account/prescriptions" },
  ],
  links: {
    facebook: "https://www.facebook.com/fastsheba.com.bd",
    website: "https://fastsheba.com.bd/",
  },
};
