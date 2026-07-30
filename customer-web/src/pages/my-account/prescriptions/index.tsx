import Link from "next/link";
import { useState } from "react";
import useSWR from "swr";
import {
  Button,
  Card,
  CardBody,
  Chip,
  Pagination,
  Select,
  SelectItem,
  Spinner,
} from "@heroui/react";
import { FileText, Plus, Upload } from "lucide-react";
import PageHead from "@/SEO/PageHead";
import MyBreadcrumbs from "@/components/custom/MyBreadcrumbs";
import PageHeader from "@/components/custom/PageHeader";
import UserLayout from "@/layouts/UserLayout";
import { getPrescriptions } from "@/routes/api";

const statusColor = (status: string) => {
  if (["approved", "fulfilled"].includes(status)) return "success";
  if (["rejected", "cancelled", "expired"].includes(status)) return "danger";
  if (status === "under_review") return "warning";
  return "primary";
};

export default function MyPrescriptionsPage() {
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState("");

  const { data, isLoading, error } = useSWR(
    ["prescriptions", page, status],
    () => getPrescriptions({ page, per_page: 10, status: status || undefined }),
    { revalidateOnFocus: false },
  );

  const prescriptions = data?.data?.data || [];
  const lastPage = data?.data?.last_page || 1;

  return (
    <>
      <PageHead pageTitle="My Prescriptions" />
      <MyBreadcrumbs
        breadcrumbs={[
          { href: "/my-account", label: "My Account" },
          { href: "/my-account/prescriptions", label: "My Prescriptions" },
        ]}
      />
      <UserLayout activeTab="prescriptions">
        <PageHeader
          title="My Prescriptions"
          subtitle="Track pharmacy review, approval and fulfilment status."
        />

        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <Select
            aria-label="Filter prescription status"
            label="Status"
            selectedKeys={[status || "all"]}
            onSelectionChange={(keys) => {
              const selected = String(Array.from(keys)[0] || "all");
              setStatus(selected === "all" ? "" : selected);
              setPage(1);
            }}
            className="max-w-xs"
          >
            <SelectItem key="all">All statuses</SelectItem>
            <SelectItem key="pending">Pending</SelectItem>
            <SelectItem key="under_review">Under review</SelectItem>
            <SelectItem key="approved">Approved</SelectItem>
            <SelectItem key="rejected">Rejected</SelectItem>
            <SelectItem key="fulfilled">Fulfilled</SelectItem>
            <SelectItem key="cancelled">Cancelled</SelectItem>
          </Select>
          <Button
            as={Link}
            href="/prescriptions"
            color="primary"
            startContent={<Upload size={17} />}
          >
            Upload Prescription
          </Button>
        </div>

        {isLoading ? (
          <div className="grid min-h-64 place-items-center">
            <Spinner color="primary" />
          </div>
        ) : error || data?.success === false ? (
          <Card shadow="none" className="border border-danger-200">
            <CardBody className="items-center gap-2 py-12 text-center">
              <FileText size={40} className="text-danger" />
              <p className="font-semibold">Could not load prescriptions</p>
              <p className="text-sm text-default-500">
                Please log in again or check the Laravel API connection.
              </p>
            </CardBody>
          </Card>
        ) : prescriptions.length === 0 ? (
          <Card shadow="none" className="border border-dashed border-primary-200">
            <CardBody className="items-center gap-3 py-14 text-center">
              <div className="grid h-14 w-14 place-items-center rounded-2xl bg-primary-50 text-primary">
                <FileText size={30} />
              </div>
              <h2 className="font-semibold">No prescriptions found</h2>
              <p className="max-w-md text-sm text-default-500">
                Upload an image or PDF and the pharmacy team will review it.
              </p>
              <Button
                as={Link}
                href="/prescriptions"
                color="primary"
                startContent={<Plus size={17} />}
              >
                Upload now
              </Button>
            </CardBody>
          </Card>
        ) : (
          <div className="space-y-3">
            {prescriptions.map((prescription) => (
              <Card
                key={prescription.uuid}
                as={Link}
                href={`/my-account/prescriptions/${prescription.uuid}`}
                isPressable
                shadow="none"
                className="w-full border border-default-200 text-left transition hover:border-primary-200 hover:shadow-sm"
              >
                <CardBody className="grid gap-4 p-4 sm:grid-cols-[auto_1fr_auto] sm:items-center">
                  <div className="grid h-12 w-12 place-items-center rounded-2xl bg-primary-50 text-primary">
                    <FileText size={24} />
                  </div>
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="font-semibold">{prescription.patient_name}</h2>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={statusColor(prescription.status) as any}
                      >
                        {prescription.status_label}
                      </Chip>
                    </div>
                    <p className="mt-1 text-xs text-default-500">
                      {prescription.files?.length || 0} file(s)
                      {prescription.doctor_name
                        ? ` · Dr. ${prescription.doctor_name}`
                        : ""}
                      {prescription.created_at
                        ? ` · ${new Date(prescription.created_at).toLocaleDateString()}`
                        : ""}
                    </p>
                  </div>
                  <span className="text-sm font-medium text-primary">View details</span>
                </CardBody>
              </Card>
            ))}

            {lastPage > 1 && (
              <div className="flex justify-center pt-5">
                <Pagination
                  page={page}
                  total={lastPage}
                  showControls
                  color="primary"
                  onChange={setPage}
                />
              </div>
            )}
          </div>
        )}
      </UserLayout>
    </>
  );
}
