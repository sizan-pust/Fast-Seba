import ServiceLandingPage from "@/components/custom/ServiceLandingPage";
import PageHead from "@/SEO/PageHead";

export default function PharmacyPage() {
  return (
    <>
      <PageHead pageTitle="Online Pharmacy" />
      <ServiceLandingPage
        eyebrow="FastSheba Pharmacy"
        title="Genuine medicine and health products, delivered"
        description="Shop nearby verified pharmacies, browse health essentials and upload a prescription from your phone or computer."
        category="pharmacy"
        banner="/brand/banners/pharmacy-prescription.jpg"
        primaryHref="/products/search?categories=pharmacy"
        primaryLabel="Shop Pharmacy"
        prescription
      />
    </>
  );
}
