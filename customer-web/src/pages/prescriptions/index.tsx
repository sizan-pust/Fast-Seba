import { FormEvent, useMemo, useRef, useState } from "react";
import { useRouter } from "next/router";
import Link from "next/link";
import NextImage from "next/image";
import { useSelector } from "react-redux";
import {
  addToast,
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Input,
  Textarea,
} from "@heroui/react";
import {
  Camera,
  CheckCircle2,
  FileText,
  Image as ImageIcon,
  LockKeyhole,
  Plus,
  ShieldCheck,
  Trash2,
  Upload,
} from "lucide-react";
import PageHead from "@/SEO/PageHead";
import { RootState } from "@/lib/redux/store";
import { createPrescription } from "@/routes/api";

const ACCEPTED = [
  "image/jpeg",
  "image/png",
  "image/webp",
  "application/pdf",
];
const MAX_FILE_SIZE = 10 * 1024 * 1024;
const MAX_FILES = 5;

export default function PrescriptionUploadPage() {
  const router = useRouter();
  const isLoggedIn = useSelector((state: RootState) => state.auth.isLoggedIn);
  const user = useSelector((state: RootState) => state.auth.user);
  const pickerRef = useRef<HTMLInputElement>(null);
  const cameraRef = useRef<HTMLInputElement>(null);
  const [files, setFiles] = useState<File[]>([]);
  const [submitting, setSubmitting] = useState(false);

  const previews = useMemo(
    () =>
      files.map((file) => ({
        file,
        url: file.type.startsWith("image/") ? URL.createObjectURL(file) : null,
      })),
    [files],
  );

  const addFiles = (incoming: FileList | null) => {
    if (!incoming) return;

    const next = [...files];

    Array.from(incoming).forEach((file) => {
      if (next.length >= MAX_FILES) return;
      if (!ACCEPTED.includes(file.type)) {
        addToast({
          title: `${file.name} is not supported`,
          description: "Use JPG, PNG, WebP or PDF.",
          color: "warning",
        });
        return;
      }
      if (file.size > MAX_FILE_SIZE) {
        addToast({
          title: `${file.name} is too large`,
          description: "Each file must be 10 MB or smaller.",
          color: "warning",
        });
        return;
      }
      if (
        !next.some(
          (current) =>
            current.name === file.name &&
            current.size === file.size &&
            current.lastModified === file.lastModified,
        )
      ) {
        next.push(file);
      }
    });

    setFiles(next.slice(0, MAX_FILES));
  };

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!isLoggedIn) {
      document.getElementById("login-btn")?.click();
      addToast({
        title: "Please log in first",
        description: "A FastSheba account is required to track your prescription.",
        color: "warning",
      });
      return;
    }

    if (files.length === 0) {
      addToast({
        title: "Prescription file required",
        description: "Take a photo or select at least one image/PDF.",
        color: "warning",
      });
      return;
    }

    const form = new FormData(event.currentTarget);
    setSubmitting(true);

    try {
      const response = await createPrescription({
        patient_name: String(form.get("patient_name") || ""),
        patient_age: String(form.get("patient_age") || ""),
        doctor_name: String(form.get("doctor_name") || ""),
        doctor_registration_no: String(
          form.get("doctor_registration_no") || "",
        ),
        prescribed_at: String(form.get("prescribed_at") || ""),
        notes: String(form.get("notes") || ""),
        files,
      });

      if (!response.success || !response.data) {
        const validation = (response as any)?.errors;
        const firstError = validation
          ? Object.values(validation).flat().at(0)
          : response.message;

        throw new Error(String(firstError || "Prescription upload failed."));
      }

      addToast({
        title: "Prescription uploaded",
        description: "You can follow its review status from My Prescriptions.",
        color: "success",
      });

      router.push(`/my-account/prescriptions/${response.data.uuid}`);
    } catch (error) {
      addToast({
        title: "Could not upload prescription",
        description:
          error instanceof Error ? error.message : "Please try again.",
        color: "danger",
      });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <PageHead pageTitle="Upload Prescription" />
      <div className="mx-auto w-full max-w-screen-xl px-2 py-6 md:px-6 md:py-10">
        <section className="mb-6 overflow-hidden rounded-3xl border border-primary-100 bg-primary-50">
          <div className="grid items-center gap-6 p-6 md:grid-cols-[1fr_auto] md:p-10">
            <div>
              <Chip
                color="primary"
                variant="flat"
                startContent={<ShieldCheck size={14} />}
              >
                Secure pharmacy workflow
              </Chip>
              <h1 className="mt-4 text-3xl font-bold text-primary-900 md:text-4xl">
                Upload a clear prescription
              </h1>
              <p className="mt-3 max-w-2xl text-sm leading-7 text-primary-900/70">
                Use your camera or upload up to five JPG, PNG, WebP or PDF files.
                The FastSheba pharmacy team can review the prescription, assign a
                seller and update its status in your account.
              </p>
            </div>
            <div className="hidden h-28 w-28 place-items-center rounded-3xl bg-white text-primary shadow-sm md:grid">
              <FileText size={52} />
            </div>
          </div>
        </section>

        <div className="grid gap-6 lg:grid-cols-[1fr_330px]">
          <Card shadow="none" className="border border-default-200">
            <CardHeader className="flex-col items-start gap-1">
              <h2 className="text-xl font-semibold">Patient & prescription details</h2>
              <p className="text-sm text-default-500">
                Fields marked required must match the prescription.
              </p>
            </CardHeader>
            <CardBody>
              <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2">
                  <Input
                    name="patient_name"
                    label="Patient name"
                    defaultValue={user?.name || ""}
                    isRequired
                    maxLength={255}
                  />
                  <Input
                    name="patient_age"
                    label="Patient age"
                    type="number"
                    min={0}
                    max={120}
                  />
                  <Input
                    name="doctor_name"
                    label="Doctor name"
                    maxLength={255}
                  />
                  <Input
                    name="doctor_registration_no"
                    label="Doctor registration number"
                    maxLength={255}
                  />
                  <Input
                    name="prescribed_at"
                    label="Prescription date"
                    type="date"
                    max={new Date().toISOString().slice(0, 10)}
                    className="sm:col-span-2"
                  />
                </div>

                <Textarea
                  name="notes"
                  label="Notes for the pharmacist"
                  placeholder="Mention allergies, preferred store, urgent needs or other useful information."
                  minRows={3}
                  maxLength={5000}
                />

                <div>
                  <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <h3 className="font-semibold">Prescription files</h3>
                      <p className="text-xs text-default-500">
                        {files.length}/{MAX_FILES} files · maximum 10 MB each
                      </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Button
                        type="button"
                        variant="bordered"
                        color="primary"
                        startContent={<Camera size={17} />}
                        onPress={() => cameraRef.current?.click()}
                      >
                        Take Photo
                      </Button>
                      <Button
                        type="button"
                        variant="flat"
                        color="primary"
                        startContent={<Upload size={17} />}
                        onPress={() => pickerRef.current?.click()}
                      >
                        Choose Files
                      </Button>
                    </div>
                  </div>

                  <input
                    ref={cameraRef}
                    type="file"
                    accept="image/*"
                    capture="environment"
                    className="hidden"
                    onChange={(event) => {
                      addFiles(event.target.files);
                      event.target.value = "";
                    }}
                  />
                  <input
                    ref={pickerRef}
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
                    multiple
                    className="hidden"
                    onChange={(event) => {
                      addFiles(event.target.files);
                      event.target.value = "";
                    }}
                  />

                  <button
                    type="button"
                    onClick={() => pickerRef.current?.click()}
                    className="grid min-h-40 w-full place-items-center rounded-2xl border-2 border-dashed border-primary-200 bg-primary-50/50 p-5 text-center transition hover:border-primary"
                  >
                    <div>
                      <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-white text-primary shadow-sm">
                        <Plus />
                      </div>
                      <p className="mt-3 font-semibold">Add prescription images or PDF</p>
                      <p className="mt-1 text-xs text-default-500">
                        Make sure the doctor’s writing, seal and medicine details are readable.
                      </p>
                    </div>
                  </button>

                  {previews.length > 0 && (
                    <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                      {previews.map(({ file, url }, index) => (
                        <div
                          key={`${file.name}-${file.lastModified}`}
                          className="relative overflow-hidden rounded-2xl border border-default-200 bg-default-50"
                        >
                          {url ? (
                            <div className="relative aspect-[4/3] w-full">
                              <NextImage
                                src={url}
                                alt={file.name}
                                fill
                                unoptimized
                                sizes="(max-width: 640px) 50vw, 33vw"
                                className="object-cover"
                              />
                            </div>
                          ) : (
                            <div className="grid aspect-[4/3] place-items-center bg-primary-50 text-primary">
                              <FileText size={38} />
                            </div>
                          )}
                          <div className="p-2 pr-10">
                            <p className="truncate text-xs font-medium">{file.name}</p>
                            <p className="text-[11px] text-default-500">
                              {(file.size / 1024 / 1024).toFixed(2)} MB
                            </p>
                          </div>
                          <Button
                            type="button"
                            isIconOnly
                            size="sm"
                            color="danger"
                            variant="flat"
                            className="absolute bottom-2 right-2"
                            aria-label={`Remove ${file.name}`}
                            onPress={() =>
                              setFiles((current) =>
                                current.filter((_, itemIndex) => itemIndex !== index),
                              )
                            }
                          >
                            <Trash2 size={15} />
                          </Button>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {!isLoggedIn && (
                  <div className="flex gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-4 text-sm">
                    <LockKeyhole className="mt-0.5 shrink-0 text-warning-600" size={20} />
                    <p>
                      You can prepare the form now. FastSheba will ask you to log in
                      before uploading so the prescription stays linked to your account.
                    </p>
                  </div>
                )}

                <Button
                  type="submit"
                  color="primary"
                  size="lg"
                  isLoading={submitting}
                  startContent={!submitting ? <Upload size={18} /> : undefined}
                  className="w-full font-semibold sm:w-auto"
                >
                  Submit Prescription
                </Button>
              </form>
            </CardBody>
          </Card>

          <aside className="space-y-4">
            <Card shadow="none" className="border border-primary-100 bg-primary-50">
              <CardBody className="gap-4">
                <h2 className="font-semibold text-primary-900">Before uploading</h2>
                {[
                  "Use good light and avoid blur.",
                  "Capture the full page, including doctor information.",
                  "Do not crop medicine names or dosage instructions.",
                  "Upload every page if the prescription has multiple pages.",
                  "Prescription approval depends on applicable pharmacy rules.",
                ].map((item) => (
                  <div key={item} className="flex gap-2 text-sm text-primary-900/75">
                    <CheckCircle2 size={17} className="mt-0.5 shrink-0 text-primary" />
                    <span>{item}</span>
                  </div>
                ))}
              </CardBody>
            </Card>

            <Card shadow="none" className="border border-default-200">
              <CardBody className="gap-3">
                <h2 className="font-semibold">Already uploaded?</h2>
                <p className="text-sm text-default-500">
                  Track review, approval, seller assignment and fulfilment from your account.
                </p>
                <Button
                  as={Link}
                  href="/my-account/prescriptions"
                  variant="bordered"
                  color="primary"
                  startContent={<ImageIcon size={17} />}
                >
                  My Prescriptions
                </Button>
              </CardBody>
            </Card>
          </aside>
        </div>
      </div>
    </>
  );
}
