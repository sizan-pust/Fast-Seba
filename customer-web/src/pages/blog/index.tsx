import Link from "next/link";
import { Button, Card, CardBody } from "@heroui/react";
import { BookOpen, HeartPulse, ShoppingBasket, Store } from "lucide-react";
import PageHead from "@/SEO/PageHead";

const topics = [
  {
    icon: HeartPulse,
    title: "Pharmacy & health",
    text: "Medicine ordering, prescription upload and health-product guidance.",
  },
  {
    icon: ShoppingBasket,
    title: "Food & grocery",
    text: "Shopping guides, local delivery tips and marketplace offers.",
  },
  {
    icon: Store,
    title: "Seller stories",
    text: "Updates from FastSheba partner stores and local businesses.",
  },
];

export default function BlogPage() {
  return (
    <>
      <PageHead pageTitle="Blog" />
      <div className="mx-auto w-full max-w-screen-xl px-2 py-8 md:px-6 md:py-12">
        <section className="rounded-3xl border border-primary-100 bg-primary-50 p-7 text-center md:p-12">
          <div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-white text-primary shadow-sm">
            <BookOpen size={30} />
          </div>
          <h1 className="mt-5 text-3xl font-bold text-primary-900">
            FastSheba Blog
          </h1>
          <p className="mx-auto mt-3 max-w-2xl text-sm leading-7 text-primary-900/70">
            The public blog section is ready. Articles can be connected to the
            backend CMS after the first customer website release.
          </p>
        </section>

        <div className="mt-7 grid gap-4 md:grid-cols-3">
          {topics.map(({ icon: Icon, title, text }) => (
            <Card key={title} shadow="none" className="border border-default-200">
              <CardBody className="gap-3 p-5">
                <div className="grid h-11 w-11 place-items-center rounded-2xl bg-primary-50 text-primary">
                  <Icon size={22} />
                </div>
                <h2 className="font-semibold">{title}</h2>
                <p className="text-sm leading-6 text-default-500">{text}</p>
              </CardBody>
            </Card>
          ))}
        </div>

        <div className="mt-8 text-center">
          <Button as={Link} href="/contact-us" color="primary">
            Suggest a topic
          </Button>
        </div>
      </div>
    </>
  );
}
