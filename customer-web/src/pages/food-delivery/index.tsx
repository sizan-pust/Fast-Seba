import ServiceLandingPage from "@/components/custom/ServiceLandingPage";
import PageHead from "@/SEO/PageHead";

export default function FoodDeliveryPage() {
  return (
    <>
      <PageHead pageTitle="Food Delivery" />
      <ServiceLandingPage
        eyebrow="Food near you"
        title="Order meals and local favourites from nearby sellers"
        description="Choose your delivery location and explore available food products, restaurants and prepared items in your service area."
        category="food"
        banner="/brand/banners/fast-delivery.jpg"
        primaryHref="/products/search?categories=food"
        primaryLabel="Explore Food"
      />
    </>
  );
}
