import ServiceLandingPage from "@/components/custom/ServiceLandingPage";
import PageHead from "@/SEO/PageHead";

export default function MarketplacePage() {
  return (
    <>
      <PageHead pageTitle="Marketplace" />
      <ServiceLandingPage
        eyebrow="FastSheba Marketplace"
        title="Everything you need from trusted local sellers"
        description="Browse categories, brands and partner stores in one location-aware multi-vendor marketplace."
        banner="/brand/banners/marketplace.jpg"
        primaryHref="/categories"
        primaryLabel="Browse Categories"
      />
    </>
  );
}
