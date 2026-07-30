import Link from "next/link";
import { Button, Card, CardBody, Image } from "@heroui/react";
import {
  ArrowRight,
  FileText,
  MapPin,
  Pill,
  ShoppingBasket,
  Store,
  UtensilsCrossed,
} from "lucide-react";

const services = [
  {
    title: "Pharmacy",
    description: "Medicines, health products and prescription support.",
    href: "/pharmacy",
    icon: Pill,
  },
  {
    title: "Food Delivery",
    description: "Discover nearby restaurants and ready-to-eat items.",
    href: "/food-delivery",
    icon: UtensilsCrossed,
  },
  {
    title: "Grocery",
    description: "Fresh groceries and daily household essentials.",
    href: "/categories?category=grocery",
    icon: ShoppingBasket,
  },
  {
    title: "Marketplace",
    description: "Browse products, brands and trusted partner stores.",
    href: "/marketplace",
    icon: Store,
  },
];

export default function FastShebaServiceGrid() {
  return (
    <section className="mx-auto w-full max-w-screen-2xl px-2 py-7 md:px-6 md:py-10">
      <div className="mb-5 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.18em] text-primary">
            FastSheba services
          </p>
          <h2 className="mt-1 text-xl font-bold md:text-2xl">
            সব সেবা, এক অ্যাপে
          </h2>
          <p className="mt-1 text-sm text-default-500">
            Select your location and shop from nearby verified sellers.
          </p>
        </div>
        <Button
          as={Link}
          href="/delivery-zones"
          variant="light"
          color="primary"
          endContent={<MapPin size={16} />}
        >
          Check delivery area
        </Button>
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {services.map(({ title, description, href, icon: Icon }) => (
          <Card
            key={title}
            as={Link}
            href={href}
            isPressable
            shadow="none"
            className="border border-primary-100 bg-white transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md dark:bg-content1"
          >
            <CardBody className="gap-3 p-4">
              <div className="grid h-11 w-11 place-items-center rounded-2xl bg-primary-50 text-primary">
                <Icon size={23} />
              </div>
              <div>
                <h3 className="font-semibold">{title}</h3>
                <p className="mt-1 hidden text-xs leading-5 text-default-500 sm:block">
                  {description}
                </p>
              </div>
              <ArrowRight size={16} className="ml-auto text-primary" />
            </CardBody>
          </Card>
        ))}
      </div>

      <div className="mt-6 overflow-hidden rounded-3xl border border-primary-100 bg-primary-50">
        <div className="grid items-center md:grid-cols-[1.05fr_.95fr]">
          <div className="p-5 sm:p-8 lg:p-10">
            <div className="mb-4 grid h-12 w-12 place-items-center rounded-2xl bg-primary text-white">
              <FileText />
            </div>
            <h2 className="text-2xl font-bold text-primary-900 md:text-3xl">
              Upload your prescription securely
            </h2>
            <p className="mt-3 max-w-xl text-sm leading-6 text-primary-900/70">
              Take a photo or upload JPG, PNG, WebP or PDF files. FastSheba’s
              pharmacy team can review the prescription, assign a verified
              seller and keep the status visible in your account.
            </p>
            <div className="mt-5 flex flex-wrap gap-3">
              <Button
                as={Link}
                href="/prescriptions"
                color="primary"
                endContent={<ArrowRight size={17} />}
              >
                Upload Prescription
              </Button>
              <Button
                as={Link}
                href="/my-account/prescriptions"
                variant="bordered"
                color="primary"
              >
                Track Prescriptions
              </Button>
            </div>
          </div>
          <Image
            src="/brand/banners/pharmacy-prescription.jpg"
            alt="FastSheba prescription upload"
            radius="none"
            removeWrapper
            className="h-full min-h-64 w-full object-cover object-center"
          />
        </div>
      </div>
    </section>
  );
}
