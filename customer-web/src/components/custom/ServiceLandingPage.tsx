import Link from "next/link";
import useSWR from "swr";
import { Button, Card, CardBody, Image, Spinner } from "@heroui/react";
import { ArrowRight, FileText, MapPin, ShieldCheck, Truck } from "lucide-react";
import ProductCard from "@/components/Cards/ProductCard";
import { getProducts } from "@/routes/api";
import { getCookie } from "@/lib/cookies";
import { staticLat, staticLng } from "@/config/constants";
import { UserLocation } from "@/components/Location/types/LocationAutoComplete.types";

type Props = {
  title: string;
  eyebrow: string;
  description: string;
  category?: string;
  banner: string;
  primaryHref: string;
  primaryLabel: string;
  prescription?: boolean;
};

export default function ServiceLandingPage({
  title,
  eyebrow,
  description,
  category,
  banner,
  primaryHref,
  primaryLabel,
  prescription = false,
}: Props) {
  const location = getCookie("userLocation") as UserLocation | null;
  const lat = location?.lat || staticLat;
  const lng = location?.lng || staticLng;

  const { data, isLoading } = useSWR(
    ["service-products", category || "all", lat, lng],
    () =>
      getProducts({
        latitude: lat,
        longitude: lng,
        categories: category,
        per_page: 12,
      }),
    { revalidateOnFocus: false },
  );

  const products = data?.data?.data || [];

  return (
    <div className="mx-auto w-full max-w-screen-2xl px-2 py-5 md:px-6 md:py-8">
      <section className="overflow-hidden rounded-3xl border border-primary-100 bg-primary-50">
        <div className="grid min-h-[390px] items-center lg:grid-cols-[.92fr_1.08fr]">
          <div className="p-6 sm:p-10 lg:p-14">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-primary">
              {eyebrow}
            </p>
            <h1 className="mt-3 max-w-2xl text-3xl font-bold leading-tight text-primary-900 sm:text-5xl">
              {title}
            </h1>
            <p className="mt-4 max-w-xl text-sm leading-7 text-primary-900/70 sm:text-base">
              {description}
            </p>
            <div className="mt-7 flex flex-wrap gap-3">
              <Button
                as={Link}
                href={primaryHref}
                color="primary"
                endContent={<ArrowRight size={17} />}
              >
                {primaryLabel}
              </Button>
              {prescription && (
                <Button
                  as={Link}
                  href="/prescriptions"
                  variant="bordered"
                  color="primary"
                  startContent={<FileText size={17} />}
                >
                  Upload Prescription
                </Button>
              )}
            </div>
            <div className="mt-8 grid max-w-xl grid-cols-3 gap-3 text-xs text-primary-900/70">
              {[
                [MapPin, "Nearby sellers"],
                [ShieldCheck, "Verified products"],
                [Truck, "Fast delivery"],
              ].map(([Icon, label]) => {
                const FeatureIcon = Icon as typeof MapPin;
                return (
                  <div key={label as string} className="flex flex-col gap-2">
                    <FeatureIcon size={20} className="text-primary" />
                    <span>{label as string}</span>
                  </div>
                );
              })}
            </div>
          </div>
          <Image
            src={banner}
            alt={title}
            radius="none"
            removeWrapper
            className="h-full min-h-[320px] w-full object-cover object-center"
          />
        </div>
      </section>

      <section className="py-9">
        <div className="mb-5 flex items-end justify-between gap-4">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-primary">
              Available near you
            </p>
            <h2 className="mt-1 text-xl font-bold sm:text-2xl">Popular products</h2>
          </div>
          <Button as={Link} href={primaryHref} variant="light" color="primary">
            View all
          </Button>
        </div>

        {isLoading ? (
          <div className="grid min-h-52 place-items-center">
            <Spinner color="primary" />
          </div>
        ) : products.length > 0 ? (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        ) : (
          <Card shadow="none" className="border border-dashed border-primary-200">
            <CardBody className="items-center gap-2 py-12 text-center">
              <p className="font-semibold">Products will appear here after catalogue upload.</p>
              <p className="max-w-lg text-sm text-default-500">
                Sellers can upload product images and inventory from the existing
                FastSheba seller panel. The website reads them from the Laravel API.
              </p>
            </CardBody>
          </Card>
        )}
      </section>
    </div>
  );
}
