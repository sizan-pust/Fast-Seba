import { useRouter } from "next/router";
import Link from "next/link";
import useSWR from "swr";
import {
  addToast,
  Button,
  Card,
  CardBody,
  Chip,
  Divider,
  Image,
  Spinner,
} from "@heroui/react";
import {
  ArrowLeft,
  CalendarDays,
  FileText,
  Stethoscope,
  Store,
  UserRound,
  XCircle,
} from "lucide-react";
import PageHead from "@/SEO/PageHead";
import MyBreadcrumbs from "@/components/custom/MyBreadcrumbs";
import UserLayout from "@/layouts/UserLayout";
import { cancelPrescription, getPrescription } from "@/routes/api";

const statusColor = (status: string) => {
  if (["approved", "fulfilled"].includes(status)) return "success";
  if (["rejected", "cancelled", "expired"].includes(status)) return "danger";
  if (status === "under_review") return "warning";
  return "primary";
};

export default function PrescriptionDetailPage() {
  const router = useRouter();
  const uuid = typeof router.query.uuid === "string" ? router.query.uuid : "";

  const { data, isLoading, mutate } = useSWR(
    uuid ? ["prescription", uuid] : null,
    () => getPrescription(uuid),
    { revalidateOnFocus: false },
  );

  const prescription = data?.data;

  const cancel = async () => {
    if (!uuid || !window.confirm("Cancel this prescription request?")) return;

    const response = await cancelPrescription(uuid);

    if (response.success) {
      addToast({ title: "Prescription cancelled", color: "success" });
      mutate(response, false);
    } else {
      addToast({
        title: "Could not cancel",
        description: response.message,
        color: "danger",
      });
    }
  };

  return (
    <>
      <PageHead pageTitle="Prescription Details" />
      <MyBreadcrumbs
        breadcrumbs={[
          { href: "/my-account", label: "My Account" },
          { href: "/my-account/prescriptions", label: "My Prescriptions" },
          { href: router.asPath, label: "Details" },
        ]}
      />
      <UserLayout activeTab="prescriptions">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <Button
            as={Link}
            href="/my-account/prescriptions"
            variant="light"
            startContent={<ArrowLeft size={17} />}
          >
            Back
          </Button>
          {prescription &&
            ["pending", "under_review"].includes(prescription.status) && (
              <Button
                color="danger"
                variant="flat"
                startContent={<XCircle size={17} />}
                onPress={cancel}
              >
                Cancel request
              </Button>
            )}
        </div>

        {isLoading ? (
          <div className="grid min-h-64 place-items-center">
            <Spinner color="primary" />
          </div>
        ) : !prescription ? (
          <Card shadow="none" className="border border-danger-200">
            <CardBody className="items-center py-14 text-center">
              <FileText size={42} className="text-danger" />
              <h1 className="mt-3 font-semibold">Prescription not found</h1>
            </CardBody>
          </Card>
        ) : (
          <div className="grid gap-5 xl:grid-cols-[1fr_340px]">
            <div className="space-y-5">
              <Card shadow="none" className="border border-default-200">
                <CardBody className="gap-5 p-5">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-xs uppercase tracking-[0.16em] text-default-500">
                        Prescription request
                      </p>
                      <h1 className="mt-1 text-2xl font-bold">
                        {prescription.patient_name}
                      </h1>
                      <p className="mt-1 text-xs text-default-500">
                        Reference: {prescription.uuid}
                      </p>
                    </div>
                    <Chip
                      variant="flat"
                      color={statusColor(prescription.status) as any}
                    >
                      {prescription.status_label}
                    </Chip>
                  </div>

                  <Divider />

                  <div className="grid gap-4 sm:grid-cols-2">
                    <Info
                      icon={UserRound}
                      label="Patient"
                      value={`${prescription.patient_name}${
                        prescription.patient_age
                          ? `, ${prescription.patient_age} years`
                          : ""
                      }`}
                    />
                    <Info
                      icon={Stethoscope}
                      label="Doctor"
                      value={
                        prescription.doctor_name
                          ? `${prescription.doctor_name}${
                              prescription.doctor_registration_no
                                ? ` (${prescription.doctor_registration_no})`
                                : ""
                            }`
                          : "Not provided"
                      }
                    />
                    <Info
                      icon={CalendarDays}
                      label="Prescription date"
                      value={prescription.prescribed_at || "Not provided"}
                    />
                    <Info
                      icon={Store}
                      label="Assigned store"
                      value={prescription.store?.name || "Waiting for assignment"}
                    />
                  </div>

                  {prescription.notes && (
                    <div className="rounded-2xl bg-default-50 p-4">
                      <p className="text-xs font-semibold uppercase tracking-wide text-default-500">
                        Customer notes
                      </p>
                      <p className="mt-2 whitespace-pre-wrap text-sm leading-6">
                        {prescription.notes}
                      </p>
                    </div>
                  )}

                  {(prescription.review_notes ||
                    prescription.rejection_reason) && (
                    <div className="rounded-2xl border border-warning-200 bg-warning-50 p-4">
                      <p className="text-xs font-semibold uppercase tracking-wide text-warning-700">
                        Pharmacy update
                      </p>
                      <p className="mt-2 whitespace-pre-wrap text-sm">
                        {prescription.rejection_reason ||
                          prescription.review_notes}
                      </p>
                    </div>
                  )}
                </CardBody>
              </Card>

              {prescription.items?.length > 0 && (
                <Card shadow="none" className="border border-default-200">
                  <CardBody className="p-5">
                    <h2 className="font-semibold">Medicines identified</h2>
                    <div className="mt-4 divide-y divide-default-200">
                      {prescription.items.map((item) => (
                        <div key={item.id || item.medicine_name} className="py-3">
                          <p className="font-medium">
                            {item.medicine_name} {item.strength || ""}
                          </p>
                          <p className="mt-1 text-xs text-default-500">
                            {[item.dosage, item.duration, item.quantity
                              ? `Qty ${item.quantity}`
                              : null]
                              .filter(Boolean)
                              .join(" · ")}
                          </p>
                          {item.instructions && (
                            <p className="mt-1 text-xs">{item.instructions}</p>
                          )}
                        </div>
                      ))}
                    </div>
                  </CardBody>
                </Card>
              )}
            </div>

            <Card shadow="none" className="h-fit border border-default-200">
              <CardBody className="gap-4 p-5">
                <h2 className="font-semibold">Uploaded files</h2>
                {prescription.files.map((file) =>
                  file.mime_type.startsWith("image/") ? (
                    <a
                      key={file.id}
                      href={file.url}
                      target="_blank"
                      rel="noreferrer"
                      className="overflow-hidden rounded-2xl border border-default-200"
                    >
                      <Image
                        src={file.url}
                        alt={file.name}
                        radius="none"
                        removeWrapper
                        className="aspect-[4/3] w-full object-contain bg-default-50"
                      />
                      <p className="truncate p-3 text-xs font-medium">{file.name}</p>
                    </a>
                  ) : (
                    <Button
                      key={file.id}
                      as="a"
                      href={file.url}
                      target="_blank"
                      rel="noreferrer"
                      variant="bordered"
                      color="primary"
                      startContent={<FileText size={17} />}
                      className="justify-start"
                    >
                      {file.name}
                    </Button>
                  ),
                )}
              </CardBody>
            </Card>
          </div>
        )}
      </UserLayout>
    </>
  );
}

function Info({
  icon: Icon,
  label,
  value,
}: {
  icon: typeof UserRound;
  label: string;
  value: string;
}) {
  return (
    <div className="flex gap-3">
      <div className="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-primary-50 text-primary">
        <Icon size={19} />
      </div>
      <div>
        <p className="text-xs text-default-500">{label}</p>
        <p className="mt-1 text-sm font-medium">{value}</p>
      </div>
    </div>
  );
}
