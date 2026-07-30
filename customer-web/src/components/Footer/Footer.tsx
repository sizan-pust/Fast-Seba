import { FC, useEffect } from "react";
import { FaFacebookF, FaInstagram, FaYoutube, FaXTwitter } from "react-icons/fa6";
import {
  FileText,
  HeartPulse,
  Mail,
  MapPin,
  Package,
  Phone,
  ShieldCheck,
  Truck,
} from "lucide-react";
import { Image } from "@heroui/react";
import { useSettings } from "@/contexts/SettingsContext";
import Link from "next/link";

const Footer: FC = () => {
  const { webSettings, isSingleVendor } = useSettings();

  const {
    siteName = "FastSheba",
    shortDescription =
      "Food, grocery, pharmacy and marketplace services from nearby trusted sellers.",
    siteCopyright = "FastSheba",
    supportEmail = "fastsheba.com.bd@gmail.com",
    supportNumber = "+8801339814081",
    address = "Bangladesh",
    siteFooterLogo = "/default-logo.png",
    facebookLink = "https://www.facebook.com/fastsheba.com.bd",
    instagramLink = "",
    xLink = "",
    youtubeLink = "",
  } = webSettings || {};

  useEffect(() => {
    if (!webSettings?.footerScript) return;

    const temp = document.createElement("div");
    temp.innerHTML = webSettings.footerScript;

    const scripts = Array.from(temp.querySelectorAll("script")).map(
      (oldScript) => {
        const script = document.createElement("script");
        if (oldScript.src) script.src = oldScript.src;
        if (oldScript.textContent) script.textContent = oldScript.textContent;
        document.body.appendChild(script);
        return script;
      },
    );

    return () => scripts.forEach((script) => script.remove());
  }, [webSettings?.footerScript]);

  const copyrightText = siteCopyright?.includes("©")
    ? siteCopyright
    : `© ${new Date().getFullYear()} ${siteCopyright || siteName}. All rights reserved.`;

  const marketplaceLinks = [
    { label: "Pharmacy", href: "/pharmacy" },
    { label: "Upload Prescription", href: "/prescriptions" },
    { label: "Food Delivery", href: "/food-delivery" },
    { label: "Marketplace", href: "/marketplace" },
    { label: "Categories", href: "/categories" },
    ...(!isSingleVendor ? [{ label: "Partner Stores", href: "/stores" }] : []),
  ];

  const companyLinks = [
    { label: "About FastSheba", href: "/about-us" },
    { label: "Contact Us", href: "/contact-us" },
    { label: "FAQs", href: "/faqs" },
    { label: "Delivery Zones", href: "/delivery-zones" },
    { label: "Blog", href: "/blog" },
    ...(!isSingleVendor
      ? [{ label: "Become a Seller", href: "/seller-register" }]
      : []),
  ];

  const policyLinks = [
    { label: "Privacy Policy", href: "/privacy-policy" },
    { label: "Terms & Conditions", href: "/terms-and-conditions" },
    { label: "Shipping Policy", href: "/shipping-policy" },
    { label: "Return & Refund", href: "/return-refund-policy" },
  ];

  return (
    <footer className="w-full bg-[#052d17] text-white">
      <div className="mx-auto w-full max-w-screen-2xl px-4 py-10 md:px-6 md:py-14">
        <div className="grid gap-9 sm:grid-cols-2 lg:grid-cols-5">
          <div className="sm:col-span-2">
            <Link href="/" className="inline-flex" aria-label="FastSheba home">
              <Image
                src={siteFooterLogo || "/default-logo.png"}
                fallbackSrc="/default-logo.png"
                alt={siteName}
                radius="none"
                classNames={{
                  img: "h-16 w-auto object-contain brightness-0 invert",
                  wrapper: "min-w-[190px]",
                }}
              />
            </Link>
            <p className="mt-4 max-w-md text-sm leading-6 text-white/70">
              {shortDescription}
            </p>

            <div className="mt-6 grid max-w-lg grid-cols-2 gap-3 text-xs text-white/75">
              {[
                [HeartPulse, "Prescription support"],
                [Truck, "Location-based delivery"],
                [ShieldCheck, "Trusted marketplace"],
                [Package, "Multi-category shopping"],
              ].map(([Icon, label]) => {
                const FeatureIcon = Icon as typeof HeartPulse;
                return (
                  <div key={label as string} className="flex items-center gap-2">
                    <FeatureIcon size={17} className="text-primary-300" />
                    <span>{label as string}</span>
                  </div>
                );
              })}
            </div>
          </div>

          <FooterColumn title="Shop" links={marketplaceLinks} />
          <FooterColumn title="Company" links={companyLinks} />
          <FooterColumn title="Policies" links={policyLinks} />
        </div>

        <div className="mt-10 grid gap-5 border-t border-white/10 pt-7 md:grid-cols-[1fr_auto] md:items-center">
          <div className="flex flex-col gap-3 text-sm text-white/70 sm:flex-row sm:flex-wrap sm:gap-6">
            <a href={`tel:${supportNumber}`} className="flex items-center gap-2 hover:text-white">
              <Phone size={16} className="text-primary-300" />
              {supportNumber}
            </a>
            <a href={`mailto:${supportEmail}`} className="flex items-center gap-2 hover:text-white">
              <Mail size={16} className="text-primary-300" />
              {supportEmail}
            </a>
            <span className="flex items-center gap-2">
              <MapPin size={16} className="text-primary-300" />
              {address}
            </span>
            <Link href="/prescriptions" className="flex items-center gap-2 font-semibold text-primary-200 hover:text-white">
              <FileText size={16} />
              Upload a prescription
            </Link>
          </div>

          <div className="flex items-center gap-2">
            {[
              [facebookLink, FaFacebookF, "Facebook"],
              [instagramLink, FaInstagram, "Instagram"],
              [xLink, FaXTwitter, "X"],
              [youtubeLink, FaYoutube, "YouTube"],
            ].map(([href, Icon, label]) =>
              href ? (
                <a
                  key={label as string}
                  href={href as string}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label={label as string}
                  className="grid h-9 w-9 place-items-center rounded-full bg-white/10 transition hover:bg-primary"
                >
                  {(() => {
                    const SocialIcon = Icon as typeof FaFacebookF;
                    return <SocialIcon size={15} />;
                  })()}
                </a>
              ) : null,
            )}
          </div>
        </div>

        <div className="mt-7 flex flex-col gap-2 border-t border-white/10 pt-5 text-xs text-white/55 sm:flex-row sm:items-center sm:justify-between">
          <span>{copyrightText}</span>
          <span>FastSheba — সব সেবা, এক অ্যাপে</span>
        </div>
      </div>
    </footer>
  );
};

function FooterColumn({
  title,
  links,
}: {
  title: string;
  links: { label: string; href: string }[];
}) {
  return (
    <div>
      <h2 className="text-sm font-semibold uppercase tracking-[0.18em] text-primary-200">
        {title}
      </h2>
      <div className="mt-4 space-y-3">
        {links.map((link) => (
          <Link
            key={link.href}
            href={link.href}
            className="block text-sm text-white/70 transition hover:translate-x-1 hover:text-white"
          >
            {link.label}
          </Link>
        ))}
      </div>
    </div>
  );
}

export default Footer;
