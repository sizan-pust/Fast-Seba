import { Settings } from "@/types/ApiResponse";

export const defaultSettings = [
  {
    variable: "system",
    value: {
      appName: "FastSheba",
      logo: "/default-logo.png",
      favicon: "/default-favicon.ico",
      copyrightDetails: "© 2026 FastSheba. All rights reserved.",
      systemTimezone: "Asia/Dhaka",
      sellerSupportNumber: "+8801339814081",
      sellerSupportEmail: "fastsheba.com.bd@gmail.com",
      systemVendorType: "multiple",
      checkoutType: "multi_store",
      minimumCartAmount: 0,
      maximumItemsAllowedInCart: 50,
      lowStockLimit: 5,
      maximumDistanceToNearestStore: "25",
      enableWallet: true,
      welcomeWalletBalanceAmount: 0,
      currency: "BDT",
      currencySymbol: "৳",
      enableThirdPartyStoreSync: false,
      Shopify: false,
      Woocommerce: false,
      etsy: false,
      sellerAppMaintenanceMode: false,
      sellerAppMaintenanceMessage: "",
      webMaintenanceMode: false,
      webMaintenanceMessage: "",
      demoMode: false,
      adminDemoModeMessage: "",
      sellerDemoModeMessage: "",
      customerDemoModeMessage: "",
      customerLocationDemoModeMessage: "",
      deliveryBoyDemoModeMessage: "",
      referEarnStatus: true,
      referEarnMethodUser: "fixed",
      referEarnBonusUser: "50",
      referEarnMaximumBonusAmountUser: "50",
      referEarnMethodReferral: "fixed",
      referEarnBonusReferral: "25",
      referEarnMaximumBonusAmountReferral: "25",
      referEarnMinimumOrderAmount: "0",
      referEarnNumberOfTimesBonus: "1",
    },
  },
  {
    variable: "authentication",
    value: {
      customSms: false,
      customSmsUrl: "",
      customSmsMethod: "POST",
      googleRecaptchaSiteKey: "",
      customSmsTokenAccountSid: "",
      customSmsAuthToken: "",
      customSmsTextFormatData: "",
      customSmsHeaderKey: [],
      customSmsHeaderValue: [],
      customSmsParamsKey: [],
      customSmsParamsValue: [],
      customSmsBodyKey: [],
      customSmsBodyValue: [],
      firebase: false,
      smsGateway: "custom",
      fireBaseApiKey: "",
      fireBaseAuthDomain: "",
      fireBaseDatabaseURL: "",
      fireBaseProjectId: "",
      fireBaseStorageBucket: "",
      fireBaseMessagingSenderId: "",
      fireBaseAppId: "",
      fireBaseMeasurementId: "",
      appleLogin: false,
      googleLogin: false,
      facebookLogin: false,
      googleApiKey: "",
    },
  },
  {
    variable: "web",
    value: {
      siteName: "FastSheba",
      siteCopyright: "© 2026 FastSheba. All rights reserved.",
      supportNumber: "+8801339814081",
      supportEmail: "fastsheba.com.bd@gmail.com",
      address: "Bangladesh",
      shortDescription:
        "Food, grocery, pharmacy and marketplace services from nearby trusted sellers.",
      siteHeaderLogo: "/default-logo.png",
      siteHeaderDarkLogo: "/default-logo.png",
      siteFooterLogo: "/default-logo.png",
      siteFavicon: "/default-favicon.ico",
      headerScript: "",
      footerScript: "",
      googleMapKey: process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY || "",
      mapIframe: "",
      appDownloadSection: true,
      appSectionTitle: "FastSheba in your pocket",
      appSectionTagline: "সব সেবা, এক অ্যাপে",
      appSectionPlaystoreLink: "",
      appSectionAppstoreLink: "",
      appSectionShortDescription:
        "Order nearby products, upload prescriptions and track deliveries.",
      facebookLink: "https://www.facebook.com/fastsheba.com.bd",
      instagramLink: "",
      xLink: "",
      youtubeLink: "",
      shippingFeatureSection: "true",
      shippingFeatureSectionTitle: "Fast local delivery",
      shippingFeatureSectionDescription:
        "Location-aware delivery from nearby verified stores.",
      returnFeatureSection: "true",
      returnFeatureSectionTitle: "Easy returns",
      returnFeatureSectionDescription:
        "Transparent return and refund workflows.",
      safetySecurityFeatureSection: "true",
      safetySecurityFeatureSectionTitle: "Secure shopping",
      safetySecurityFeatureSectionDescription:
        "Protected checkout and trusted sellers.",
      supportFeatureSection: "true",
      supportFeatureSectionTitle: "Customer support",
      supportFeatureSectionDescription:
        "Support for orders, pharmacy and delivery issues.",
      metaKeywords:
        "FastSheba, pharmacy delivery Bangladesh, grocery delivery, food delivery, marketplace, upload prescription",
      metaDescription:
        "FastSheba is a Bangladesh multi-vendor marketplace for pharmacy, food, grocery and daily essentials.",
      defaultLatitude: "23.8103",
      defaultLongitude: "90.4125",
      enableCountryValidation: true,
      allowedCountries: ["BD"],
      returnRefundPolicy: "",
      shippingPolicy: "",
      privacyPolicy: "",
      termsCondition: "",
      aboutUs:
        "<h2>FastSheba</h2><p>Food, grocery, pharmacy and marketplace services in one location-aware platform.</p>",
    },
  },
  {
    variable: "payment",
    value: {
      stripePayment: false,
      stripePaymentMode: "test",
      stripePublishableKey: "",
      stripeCurrencyCode: "BDT",
      razorpayPayment: false,
      razorpayPaymentMode: "test",
      razorpayKeyId: "",
      paystackPayment: false,
      paystackPaymentMode: "test",
      paystackPublicKey: "",
      cod: true,
      directBankTransfer: false,
      bankAccountName: "",
      bankAccountNumber: "",
      bankName: "",
      bankCode: "",
      bankExtraNote: "",
      flutterwavePayment: false,
      flutterwavePaymentMode: "test",
      flutterwavePublicKey: "",
      flutterwaveCurrencyCode: "BDT",
      wallet: true,
    },
  },
  {
    variable: "notification",
    value: {
      firebaseProjectId: "",
      serviceAccountFile: "",
      vapIdKey: "",
    },
  },
  {
    variable: "app",
    value: {
      appstoreLink: "",
      playstoreLink: "",
      appScheme: "fastsheba",
      appDomainName: "fastsheba.com.bd",
      customerAppScheme: "fastsheba",
      customerAppstoreLink: "",
      customerPlaystoreLink: "",
      sellerAppScheme: "fastsheba-seller",
      sellerAppstoreLink: "",
      sellerPlaystoreLink: "",
    },
  },
  {
    variable: "home_general_settings",
    value: {
      title: "Everything nearby, delivered",
      searchLabels: ["medicine", "grocery", "food", "daily essentials"],
      backgroundType: "color",
      backgroundColor: "#EAF8EE",
      backgroundImage: "",
      icon: "",
      activeIcon: "",
      fontColor: "#064E24",
    },
  },
  {
    variable: "advertisement",
    value: {
      featureEnabled: false,
      disableBehavior: "hide",
      cpcRate: 0,
      walletMinTopup: 0,
      searchSlotCount: 0,
      relatedSlotCount: 0,
      impressionMultiplierMin: 1,
      impressionMultiplierMax: 1,
      adImpressionVisibilityPct: 50,
      adImpressionVisibilityMs: 1000,
      broadcastDriver: "log",
      pusherAppId: "",
      pusherKey: "",
      pusherSecret: "",
      pusherCluster: "",
      reverbAppId: "",
      reverbKey: "",
      reverbSecret: "",
      reverbHost: "",
      reverbPort: null,
      reverbScheme: "https",
    },
  },
] as unknown as Settings;

export function mergeSettings(remote: unknown): Settings {
  const remoteRows = Array.isArray(remote) ? remote : [];
  const remoteByVariable = new Map(
    remoteRows
      .filter(
        (row): row is { variable: string; value: Record<string, unknown> } =>
          Boolean(row) &&
          typeof row === "object" &&
          typeof row.variable === "string" &&
          row.value !== null &&
          typeof row.value === "object",
      )
      .map((row) => [row.variable, row.value]),
  );

  return defaultSettings.map((row) => {
    const remoteValue = remoteByVariable.get(row.variable);

    return {
      variable: row.variable,
      value: {
        ...(row.value as Record<string, unknown>),
        ...(remoteValue || {}),
      },
    };
  }) as unknown as Settings;
}
