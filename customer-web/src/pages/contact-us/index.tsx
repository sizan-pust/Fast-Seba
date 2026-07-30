import Link from "next/link";
import {
  Button,
  Card,
  CardBody,
  Input,
  Textarea,
} from "@heroui/react";
import { Mail, MapPin, MessageCircle, Phone } from "lucide-react";
import PageHead from "@/SEO/PageHead";
import { useSettings } from "@/contexts/SettingsContext";

export default function ContactPage() {
  const { webSettings } = useSettings();
  const phone = webSettings?.supportNumber || "+8801339814081";
  const email =
    webSettings?.supportEmail || "fastsheba.com.bd@gmail.com";

  return (
    <>
      <PageHead pageTitle="Contact Us" />
      <div className="mx-auto w-full max-w-screen-xl px-2 py-8 md:px-6 md:py-12">
        <section className="rounded-3xl bg-primary-50 p-6 md:p-10">
          <p className="text-xs font-semibold uppercase tracking-[0.18em] text-primary">
            FastSheba support
          </p>
          <h1 className="mt-2 text-3xl font-bold text-primary-900 md:text-4xl">
            How can we help?
          </h1>
          <p className="mt-3 max-w-2xl text-sm leading-7 text-primary-900/70">
            Contact us about orders, pharmacy and prescription support,
            delivery, seller onboarding or account issues.
          </p>
        </section>

        <div className="mt-6 grid gap-5 lg:grid-cols-[360px_1fr]">
          <div className="space-y-4">
            {[
              [Phone, "Call us", phone, `tel:${phone}`],
              [Mail, "Email us", email, `mailto:${email}`],
              [MapPin, "Service country", webSettings?.address || "Bangladesh", "/delivery-zones"],
              [MessageCircle, "Frequently asked questions", "Get quick answers", "/faqs"],
            ].map(([Icon, title, text, href]) => {
              const ContactIcon = Icon as typeof Phone;
              return (
                <Card key={title as string} shadow="none" className="border border-default-200">
                  <CardBody className="flex-row items-center gap-4 p-4">
                    <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-primary-50 text-primary">
                      <ContactIcon size={21} />
                    </div>
                    <div className="min-w-0">
                      <p className="text-xs text-default-500">{title as string}</p>
                      <Link
                        href={href as string}
                        className="mt-1 block truncate text-sm font-semibold hover:text-primary"
                      >
                        {text as string}
                      </Link>
                    </div>
                  </CardBody>
                </Card>
              );
            })}
          </div>

          <Card shadow="none" className="border border-default-200">
            <CardBody className="gap-4 p-5 md:p-7">
              <h2 className="text-xl font-semibold">Send a message</h2>
              <p className="text-sm text-default-500">
                This form opens your email app. Logged-in customer support
                tickets can be connected in the next support UI batch.
              </p>
              <form
                action={`mailto:${email}`}
                method="get"
                encType="text/plain"
                className="grid gap-4 sm:grid-cols-2"
              >
                <Input name="name" label="Name" isRequired />
                <Input name="email" type="email" label="Email" isRequired />
                <Input
                  name="subject"
                  label="Subject"
                  isRequired
                  className="sm:col-span-2"
                />
                <Textarea
                  name="body"
                  label="Message"
                  minRows={6}
                  isRequired
                  className="sm:col-span-2"
                />
                <Button type="submit" color="primary" className="sm:w-fit">
                  Open Email App
                </Button>
              </form>
            </CardBody>
          </Card>
        </div>
      </div>
    </>
  );
}
