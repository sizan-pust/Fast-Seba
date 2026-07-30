import React, { useState, useRef, useEffect, useCallback } from "react";
import {
  Button,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
  addToast,
  Alert,
} from "@heroui/react";
import { ChevronDown, MapPin } from "lucide-react";
import LocationAutoComplete from "./LocationAutoComplete";
import GoogleMap from "./GoogleMap";
import {
  LocationSelection,
  UserLocation,
} from "./types/LocationAutoComplete.types";
import { deleteCookie, getCookie, setCookie } from "@/lib/cookies";
import { handleCheckZone } from "@/helpers/functionalHelpers";
import { useSettings } from "@/contexts/SettingsContext";
import { onLocationChange } from "@/helpers/events";
import { useTranslation } from "react-i18next";
import useSWR from "swr";
import { staticLat, staticLng } from "@/config/constants";
import { debounce } from "lodash";
import { useMemo } from "react";
import { getDeliveryZones, getStoresByMap } from "@/routes/api";
import { Store } from "@/types/ApiResponse";

// Define the ref interface (should match LocationAutoComplete)
interface LocationAutoCompleteRef {
  setInputValue: (value: string) => void;
}

// Removed MapStoreCard since user requested not to show cards here

const LocationSelector = () => {
  const {
    defaultLocation,
    demoMode,
    systemSettings,
    isSingleVendor,
    webSettings,
  } = useSettings();
  const hasGoogleMaps = Boolean(
    webSettings?.googleMapKey || process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY,
  );
  const { t } = useTranslation();
  const [selectedLatLng, setSelectedLatLng] = useState<{
    lat: number;
    lng: number;
  } | null>(defaultLocation);

  const [selectedLocation, setSelectedLocation] =
    useState<LocationSelection | null>(null);
  const [selectedDeliveryAvailable, setSelectedDeliveryAvailable] = useState<
    boolean | null
  >(null);

  // Temporary state for modal - only updates main state when confirmed
  const [tempSelectedLatLng, setTempSelectedLatLng] = useState<{
    lat: number;
    lng: number;
  } | null>(null);

  const [tempSelectedLocation, setTempSelectedLocation] =
    useState<LocationSelection | null>(null);
  const [tempDeliveryAvailable, setTempDeliveryAvailable] = useState<
    boolean | null
  >(null);

  const [isInitialized, setIsInitialized] = useState(false);
  const [deliveryCheckLoading, setDeliveryCheckLoading] = useState(false);
  const [stores, setStores] = useState<Store[]>([]);
  const [mapLoaded, setMapLoaded] = useState(false);
  const { isOpen, onOpen, onClose } = useDisclosure();

  useEffect(() => {
    if (isOpen && !hasGoogleMaps) {
      setMapLoaded(true);
    }
  }, [hasGoogleMaps, isOpen]);
  const [viewState, setViewState] = useState<
    "location_selection" | "store_view"
  >("location_selection");
  const viewStateRef = useRef(viewState);
  const lastBoundsRef = useRef<{
    ne: { lat: number; lng: number };
    sw: { lat: number; lng: number };
  } | null>(null);

  // Debounce API call to prevent laggy behavior during zoom/pan
  const debouncedFetchStores = useMemo(
    () =>
      debounce(
        async (bounds: {
          ne: { lat: number; lng: number };
          sw: { lat: number; lng: number };
        }) => {
          if (isSingleVendor) return;
          try {
            const res = await getStoresByMap({
              ne_lat: bounds.ne.lat,
              ne_lng: bounds.ne.lng,
              sw_lat: bounds.sw.lat,
              sw_lng: bounds.sw.lng,
            });

            if (res.success && res.data) {
              setStores(res.data.stores);
            }
          } catch (error) {
            console.error("Error fetching stores by map:", error);
          }
        },
        500, // 500ms delay
      ),
    [isSingleVendor],
  );

  useEffect(() => {
    viewStateRef.current = viewState;
    if (
      viewState === "store_view" &&
      lastBoundsRef.current &&
      !isSingleVendor
    ) {
      debouncedFetchStores(lastBoundsRef.current);
    }
  }, [viewState, isSingleVendor, debouncedFetchStores]);

  // Fetch delivery zones only when modal is open AND map is loaded properly
  const { data: zonesData } = useSWR(
    isOpen && mapLoaded ? "delivery-zones" : null,
    () => getDeliveryZones({ per_page: 100 }),
  );
  const zones = zonesData?.success ? zonesData.data.data : [];

  // Create a ref for LocationAutoComplete
  const autocompleteRef = useRef<LocationAutoCompleteRef>(null);

  // Initialize component with cookie data
  useEffect(() => {
    const initializeLocation = () => {
      try {
        const deliveryLocation = getCookie("userLocation") as
          | UserLocation
          | undefined;
        const browsingLocation = getCookie("browseLocation") as
          | UserLocation
          | undefined;
        const savedLocation = deliveryLocation || browsingLocation;

        if (savedLocation?.lat && savedLocation?.lng) {
          const locationData: LocationSelection = {
            placeName: savedLocation.placeName || "Selected Location",
            latLng: { lat: savedLocation.lat, lng: savedLocation.lng },
            placeDescription: savedLocation.placeDescription || "",
            accuracyMeters: savedLocation.accuracyMeters,
            source: savedLocation.source || "saved",
          };

          setSelectedLatLng(locationData.latLng);
          setSelectedLocation(locationData);
          setSelectedDeliveryAvailable(Boolean(deliveryLocation));
        }
      } catch (error) {
        console.error("Error initializing location from cookie:", error);
      } finally {
        setIsInitialized(true);
      }
    };

    initializeLocation();
  }, []);

  // Initialize temp state when modal opens.
  // Do not include temp state in deps, otherwise user-picked temp values get reset.
  useEffect(() => {
    if (!isOpen) return;

    setTempSelectedLatLng(selectedLatLng);
    setTempSelectedLocation(selectedLocation);
    setTempDeliveryAvailable(selectedDeliveryAvailable);
    setMapLoaded(false); // Reset map loaded state when opening modal

    setViewState("location_selection");

    // Update autocomplete input when modal opens
    if (selectedLocation && autocompleteRef.current) {
      setTimeout(() => {
        if (autocompleteRef.current) {
          autocompleteRef.current.setInputValue(selectedLocation.placeName);
        }
      }, 100);
    }
  }, [isOpen, selectedDeliveryAvailable, selectedLatLng, selectedLocation]);

  const handleLocationSelect = async (location: LocationSelection) => {
    setTempSelectedLatLng(location.latLng);
    setTempSelectedLocation(location);
    setTempDeliveryAvailable(null);
    setDeliveryCheckLoading(true);

    try {
      const available = await handleCheckZone(
        location.latLng.lat,
        location.latLng.lng,
      );
      setTempDeliveryAvailable(available);

      if (
        location.source === "device" &&
        location.accuracyMeters &&
        location.accuracyMeters > 5000
      ) {
        addToast({
          title: "Location may be approximate",
          color: "warning",
          description:
            "Your device reported a low-accuracy location. Search your address manually before checkout.",
        });
      }

      addToast({
        title: available
          ? t("locationSelector.deliveryAvailable")
          : "Location saved for browsing",
        color: available ? "success" : "warning",
        description: available
          ? undefined
          : "Delivery is not active here yet. You can browse the website and choose a supported address before checkout.",
      });
    } catch (error) {
      console.error("Error checking delivery zone:", error);
      setTempDeliveryAvailable(false);
      addToast({
        title: "Could not verify delivery",
        color: "warning",
        description:
          "You can continue browsing and verify a delivery address at checkout.",
      });
    } finally {
      setDeliveryCheckLoading(false);
    }
  };

  // Helper function to wait for Google Maps API to load
  const waitForGoogleMaps = (timeout = 5000): Promise<boolean> => {
    return new Promise((resolve) => {
      const startTime = Date.now();

      const checkGoogleMaps = () => {
        if (window.google?.maps?.Geocoder) {
          resolve(true);
        } else if (Date.now() - startTime > timeout) {
          resolve(false);
        } else {
          setTimeout(checkGoogleMaps, 200);
        }
      };

      checkGoogleMaps();
    });
  };

  const handleMapLocationUpdate = useCallback(
    async (
      latLng: {
        lat: number;
        lng: number;
      },
      renderToast: boolean = true,
    ) => {
      if (!hasGoogleMaps || !window.google?.maps?.Geocoder) {
        const placeName = `Selected location (${latLng.lat.toFixed(5)}, ${latLng.lng.toFixed(5)})`;
        const newLocation: LocationSelection = {
          placeName,
          latLng,
          placeDescription: "",
          source: "map",
        };

        setTempSelectedLatLng(latLng);
        setTempSelectedLocation(newLocation);
        autocompleteRef.current?.setInputValue(placeName);

        setDeliveryCheckLoading(true);
        try {
          const available = await handleCheckZone(latLng.lat, latLng.lng);
          setTempDeliveryAvailable(available);
          if (renderToast) {
            addToast({
              title: available ? "Delivery Available" : "Delivery Not Available",
              color: available ? "success" : "warning",
              description: available
                ? "This location is inside an active FastSheba delivery zone."
                : "You can continue browsing or select another location.",
            });
          }
        } finally {
          setDeliveryCheckLoading(false);
        }
        return;
      }

      // Wait for Google Maps API to load
      const isLoaded = await waitForGoogleMaps();
      if (!isLoaded) {
        console.warn("Google Maps API failed to load");
        return;
      }

      setDeliveryCheckLoading(true);

      try {
        const geocoder = new window.google.maps.Geocoder();
        const result = await geocoder.geocode({ location: latLng });

        if (result?.results[0]) {
          const newLocation: LocationSelection = {
            placeName: result.results[0].formatted_address,
            latLng,
            placeDescription: "",
            source: "map",
          };

          // First update the modal location and autocomplete input
          setTempSelectedLatLng(latLng);
          setTempSelectedLocation(newLocation);

          if (autocompleteRef.current) {
            autocompleteRef.current.setInputValue(newLocation.placeName);
          }

          // Then check delivery
          const res = await handleCheckZone(latLng.lat, latLng.lng);
          setTempDeliveryAvailable(res);

          if (res) {
            if (renderToast) {
              addToast({ title: "Delivery Available", color: "success" });
            }
          } else {
            addToast({
              title: "Outside delivery area",
              color: "warning",
              description:
                "You can continue browsing or select a supported delivery location.",
            });
          }
        }
      } catch (error) {
        console.error("Error geocoding map location:", error);
        addToast({
          title: "Error processing location",
          color: "danger",
          description: "Please try again",
        });
      } finally {
        setDeliveryCheckLoading(false);
      }
    },
    [hasGoogleMaps],
  );

  const persistBrowsingLocation = (location: LocationSelection) => {
    const browsingLocation: UserLocation = {
      lat: location.latLng.lat,
      lng: location.latLng.lng,
      placeName: location.placeName,
      placeDescription: location.placeDescription,
      isDeliverable: false,
      accuracyMeters: location.accuracyMeters,
      source: location.source || "saved",
    };

    deleteCookie("userLocation");
    setCookie<UserLocation>("browseLocation", browsingLocation);
    setCookie("locationPromptDismissed", true);
  };

  const handleConfirmLocation = async () => {
    if (!tempSelectedLocation || !tempSelectedLatLng) return;

    setDeliveryCheckLoading(true);

    try {
      const finalLatLng = demoMode
        ? {
            lat: defaultLocation?.lat || staticLat,
            lng: defaultLocation?.lng || staticLng,
          }
        : tempSelectedLatLng;
      const finalLocation: LocationSelection = demoMode
        ? {
            placeName: "Dhaka, Bangladesh",
            latLng: finalLatLng,
            placeDescription: "",
            source: "saved",
          }
        : tempSelectedLocation;

      const available = await handleCheckZone(finalLatLng.lat, finalLatLng.lng);

      setSelectedLatLng(finalLatLng);
      setSelectedLocation(finalLocation);
      setSelectedDeliveryAvailable(available);
      setTempDeliveryAvailable(available);

      if (available) {
        const userLocation: UserLocation = {
          lat: finalLatLng.lat,
          lng: finalLatLng.lng,
          placeName: finalLocation.placeName,
          placeDescription: finalLocation.placeDescription,
          isDeliverable: true,
          accuracyMeters: finalLocation.accuracyMeters,
          source: finalLocation.source || "saved",
        };

        setCookie<UserLocation>("userLocation", userLocation);
        deleteCookie("browseLocation");
        deleteCookie("locationPromptDismissed");
      } else {
        persistBrowsingLocation(finalLocation);
      }

      onLocationChange();
      onClose();

      addToast({
        title: available
          ? "Delivery location confirmed"
          : "Browsing location saved",
        color: available ? "success" : "warning",
        description: available
          ? "FastSheba delivery is available at this location."
          : "You can browse now. A supported address will be required before checkout.",
      });
    } catch (error) {
      console.error("Error confirming location:", error);
      persistBrowsingLocation(tempSelectedLocation);
      setSelectedLatLng(tempSelectedLatLng);
      setSelectedLocation(tempSelectedLocation);
      setSelectedDeliveryAvailable(false);
      onLocationChange();
      onClose();
      addToast({
        title: "Location saved for browsing",
        color: "warning",
        description:
          "Delivery could not be verified. Choose a supported address before checkout.",
      });
    } finally {
      setDeliveryCheckLoading(false);
    }
  };

  const handleBrowseWebsite = () => {
    if (tempSelectedLocation) {
      persistBrowsingLocation(tempSelectedLocation);
      setSelectedLatLng(tempSelectedLatLng);
      setSelectedLocation(tempSelectedLocation);
      setSelectedDeliveryAvailable(false);
    } else {
      setCookie("locationPromptDismissed", true);
    }

    onLocationChange();
    onClose();
    addToast({
      title: "Browse mode enabled",
      color: "default",
      description: "Select a supported delivery address when you are ready to checkout.",
    });
  };

  const handleBoundsChange = useCallback(
    (bounds: {
      ne: { lat: number; lng: number };
      sw: { lat: number; lng: number };
    }) => {
      lastBoundsRef.current = bounds;
      if (isSingleVendor) return;
      if (viewStateRef.current === "store_view") {
        debouncedFetchStores(bounds);
      }
    },
    [debouncedFetchStores, isSingleVendor],
  );

  const handleZoomChange = useCallback(() => {
    // Smooth zoom handling logic
    if (window.google?.maps) {
      // const map = window.google.maps;
    }
  }, []);

  const handleCloseModal = () => {
    setTempSelectedLatLng(selectedLatLng);
    setTempSelectedLocation(selectedLocation);
    setTempDeliveryAvailable(selectedDeliveryAvailable);

    if (!selectedLocation) {
      setCookie("locationPromptDismissed", true);
    }

    onClose();
  };

  // Get display text for the button
  const getButtonText = () => {
    if (!isInitialized) return t("locationSelector.getting");
    if (selectedLocation) {
      const displayText = selectedLocation.placeDescription
        ? `${selectedLocation.placeName}, ${selectedLocation.placeDescription}`
        : selectedLocation.placeName;
      const statusText =
        selectedDeliveryAvailable === false
          ? `${displayText} (browse only)`
          : displayText;
      return statusText.length > 34
        ? `${statusText.substring(0, 34)}...`
        : statusText;
    }
    return t("locationSelector.selectLocation");
  };

  return (
    <div>
      <button
        id="location-modal-btn"
        onClick={() => {
          onOpen();
          if (defaultLocation) {
            // Call after modal opens and map loads
            handleMapLocationUpdate(defaultLocation, false);
          }
        }}
      />
      <Button
        disableRipple
        color={
          !isInitialized
            ? "warning"
            : selectedDeliveryAvailable === false
              ? "warning"
              : selectedLocation
                ? undefined
                : "primary"
        }
        variant={selectedLocation ? "flat" : "flat"}
        onPress={onOpen}
        className="p-0 py-0 bg-transparent max-w-full"
        startContent={<MapPin width={16} />}
        endContent={<ChevronDown width={16} />}
        isDisabled={!isInitialized}
        fullWidth
      >
        <span className="truncate text-left flex-1">{getButtonText()}</span>
      </Button>

      <Modal
        isOpen={isOpen}
        onClose={handleCloseModal}
        scrollBehavior="inside"
        isDismissable
        classNames={{
          base: "w-full overflow-hidden",
          body: "px-2 md:px-4",
          header: "p-3 sm:p-4",
        }}
        size="3xl"
        backdrop="blur"
      >
        <ModalContent>
          <ModalHeader className="flex justify-between items-center">
            <span>{t("locationSelector.modalTitle")}</span>
          </ModalHeader>
          <ModalBody className="pb-0 bg-default-50 dark:bg-content1 flex flex-col gap-0 px-2 md:px-4">
            {viewState === "location_selection" && (
              <div className="w-full mb-3 mt-1">
                <LocationAutoComplete
                  onLocationSelect={handleLocationSelect}
                  ref={autocompleteRef}
                  initialLocation={tempSelectedLocation}
                />
              </div>
            )}
            {tempDeliveryAvailable === false && (
              <Alert
                color="warning"
                variant="faded"
                title="Delivery is not available at this location yet"
                description="You can still browse FastSheba. Search for another location now, or choose a supported delivery address before checkout."
                className="mb-3"
              />
            )}
            {
              tempSelectedLocation?.source === "device" &&
              tempSelectedLocation.accuracyMeters &&
              tempSelectedLocation.accuracyMeters > 5000 && (
                <Alert
                  color="warning"
                  variant="flat"
                  title="Your device location is approximate"
                  description={`Accuracy is about ${Math.round(
                    tempSelectedLocation.accuracyMeters / 1000,
                  )} km. Search your address manually for reliable delivery checking.`}
                  className="mb-3"
                />
              )
            }
            <div className="relative w-full rounded-xl overflow-hidden min-h-[450px]">
              {hasGoogleMaps ? (
                <GoogleMap
                  latLng={tempSelectedLatLng}
                  onLocationUpdate={handleMapLocationUpdate}
                  onBoundsChange={isSingleVendor ? undefined : handleBoundsChange}
                  onZoomChange={isSingleVendor ? undefined : handleZoomChange}
                  stores={
                    isSingleVendor || viewState === "location_selection"
                      ? []
                      : stores
                  }
                  zones={viewState === "store_view" ? [] : zones}
                  onMapLoad={() => setMapLoaded(true)}
                  disableRedirect={!selectedLocation}
                  disableLocationChange={viewState === "store_view"}
                  height={450}
                />
              ) : (
                <div className="grid min-h-[450px] place-items-center bg-primary-50 p-6 text-center">
                  <div className="max-w-lg">
                    <div className="mx-auto grid h-16 w-16 place-items-center rounded-3xl bg-white text-primary shadow-sm">
                      <MapPin size={32} />
                    </div>
                    <h3 className="mt-5 text-lg font-semibold text-primary-900">
                      Browser location is ready
                    </h3>
                    <p className="mt-2 text-sm leading-6 text-primary-900/70">
                      Press the location icon in the search field to use GPS.
                      Add NEXT_PUBLIC_GOOGLE_MAPS_API_KEY to enable address
                      autocomplete, draggable maps and reverse geocoding.
                    </p>
                    {tempSelectedLatLng && (
                      <div className="mt-5 rounded-2xl bg-white p-4 text-sm shadow-sm">
                        <p className="font-semibold">Selected coordinates</p>
                        <p className="mt-1 text-default-500">
                          {tempSelectedLatLng.lat.toFixed(6)},{" "}
                          {tempSelectedLatLng.lng.toFixed(6)}
                        </p>
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>
          </ModalBody>
          <ModalFooter className="flex flex-col bg-white dark:bg-content1 px-4 py-4 gap-4 w-full m-0 z-30">
            {demoMode && (
              <div className="w-full">
                <Alert
                  color="warning"
                  title={
                    systemSettings?.customerLocationDemoModeMessage
                      ? systemSettings?.customerLocationDemoModeMessage
                      : "Demo mode is enabled. Location will default automatically."
                  }
                  variant="faded"
                  classNames={{
                    title: "text-xs",
                    base: "py-0 max-w-fit",
                    alertIcon: "w-5",
                    iconWrapper: "w-5 h-5",
                  }}
                />
              </div>
            )}

            {viewState === "location_selection" ? (
              <>
                <div className="flex items-center gap-3 w-full">
                  <div className="w-10 h-10 rounded-full bg-primary-50 dark:bg-primary-900/30 text-primary flex items-center justify-center shrink-0">
                    <MapPin size={20} className="text-primary" />
                  </div>
                  <div className="flex flex-col flex-1 overflow-hidden">
                    <span className="text-xs text-default-500">
                      {t(
                        "locationSelector.currentLocation",
                        "Current Location",
                      )}
                    </span>
                    <span className="text-sm font-semibold line-clamp-1 truncate text-left w-full">
                      {tempSelectedLocation?.placeName || getButtonText()}
                    </span>
                  </div>
                </div>
                <div className="flex gap-3 w-full">
                  <Button
                    className="flex-1 font-medium bg-white dark:bg-default-100 border-1 border-gray-300 dark:border-default-200"
                    variant="bordered"
                    onPress={() => {
                      if (tempDeliveryAvailable === true && selectedLocation) {
                        setTempSelectedLatLng(selectedLatLng);
                        setTempSelectedLocation(selectedLocation);
                        setViewState("store_view");
                      } else {
                        handleBrowseWebsite();
                      }
                    }}
                  >
                    {tempDeliveryAvailable === true && selectedLocation
                      ? t("browseStores", "Browse Stores")
                      : "Browse Website"}
                  </Button>
                  <Button
                    color="primary"
                    className="flex-1 font-medium"
                    onPress={() => handleConfirmLocation()}
                    isDisabled={!tempSelectedLocation || deliveryCheckLoading}
                    isLoading={deliveryCheckLoading}
                  >
                    {deliveryCheckLoading
                      ? t("locationSelector.checking", "Checking...")
                      : tempDeliveryAvailable === false
                        ? "Save & Browse"
                        : t(
                            "locationSelector.confirmLocation",
                            "Confirm Location",
                          )}
                  </Button>
                </div>
              </>
            ) : (
              <div className="flex items-center gap-3 w-full">
                <div className="w-10 h-10 rounded-full bg-primary-50 dark:bg-primary-900/30 text-primary flex items-center justify-center shrink-0">
                  <MapPin size={20} className="text-primary" />
                </div>
                <div className="flex flex-col flex-1 overflow-hidden">
                  <span className="text-xs text-default-500">
                    {t("locationSelector.currentLocation", "Current Location")}
                  </span>
                  <span className="text-sm font-semibold line-clamp-1 truncate text-left w-full">
                    {tempSelectedLocation?.placeName || getButtonText()}
                  </span>
                </div>
                <Button
                  variant="bordered"
                  className="font-medium bg-white dark:bg-default-100 border-1 border-gray-300 dark:border-default-200 px-6 shrink-0"
                  onPress={() => setViewState("location_selection")}
                >
                  {t("change", "Change")}
                </Button>
              </div>
            )}
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
};

export default LocationSelector;
