import {
  Banner,
  BannerData,
  FeaturedSection,
  PaginatedResponse,
  Product,
  ProductFaq,
  ProductReviews,
  Review,
} from "@/types/ApiResponse";

const emptyPage = <T>(data: T, page = 1, perPage = 20) => ({
  current_page: page,
  data,
  last_page: 1,
  per_page: perPage,
  total: Array.isArray(data) ? data.length : 0,
});

export function collectionToPaginated<T>(
  response: any,
  fallback: T,
  page = 1,
  perPage = 20,
): PaginatedResponse<T> {
  const payload = response?.data;

  if (
    payload &&
    typeof payload === "object" &&
    "current_page" in payload &&
    "data" in payload
  ) {
    return response as PaginatedResponse<T>;
  }

  return {
    success: Boolean(response?.success),
    message: response?.message || "",
    data: emptyPage((payload ?? fallback) as T, page, perPage),
  };
}

const positionBucket = (position?: string): keyof BannerData => {
  switch (position) {
    case "home_carousel":
    case "carousel":
      return "carousel";
    case "home_sidebar":
    case "sidebar":
      return "sidebar";
    default:
      return "top";
  }
};

export function normalizeBanners(response: any): PaginatedResponse<BannerData> {
  const rows: Banner[] = Array.isArray(response?.data)
    ? response.data
    : Array.isArray(response?.data?.data)
      ? response.data.data
      : [];

  const grouped: BannerData = { top: [], carousel: [], sidebar: [] };

  rows.forEach((row: any) => {
    const normalized: Banner = {
      ...row,
      type_id:
        row.type_id ??
        row.product_id ??
        row.category_id ??
        row.brand_id ??
        0,
      image:
        row.image ||
        row.banner_image ||
        "/brand/banners/pharmacy-prescription.jpg",
      banner_image:
        row.banner_image ||
        row.image ||
        "/brand/banners/pharmacy-prescription.jpg",
    };
    const bucket = positionBucket(row.position);

    if (bucket === "sidebar") {
      (grouped.sidebar ??= []).push(normalized);
    } else {
      grouped[bucket].push(normalized);
    }
  });

  // Local visual fallbacks keep the FastSheba layout usable before content is
  // uploaded in the admin panel.
  if (grouped.top.length === 0) {
    grouped.top = [
      {
        id: -1,
        type: "custom",
        type_id: 0,
        image: "/brand/banners/pharmacy-prescription.jpg",
        banner_image: "/brand/banners/pharmacy-prescription.jpg",
        title: "Medicine delivered to your doorstep",
        custom_url: "/prescriptions",
      },
      {
        id: -2,
        type: "custom",
        type_id: 0,
        image: "/brand/banners/grocery-essentials.jpg",
        banner_image: "/brand/banners/grocery-essentials.jpg",
        title: "Fresh groceries and daily essentials",
        custom_url: "/categories?category=grocery",
      },
    ];
  }

  if (grouped.carousel.length === 0) {
    grouped.carousel = [
      {
        id: -3,
        type: "custom",
        type_id: 0,
        image: "/brand/banners/marketplace.jpg",
        banner_image: "/brand/banners/marketplace.jpg",
        title: "Everything you need in one marketplace",
        custom_url: "/categories",
      },
      {
        id: -4,
        type: "custom",
        type_id: 0,
        image: "/brand/banners/fast-delivery.jpg",
        banner_image: "/brand/banners/fast-delivery.jpg",
        title: "Fast local delivery",
        custom_url: "/delivery-zones",
      },
    ];
  }

  return {
    success: response?.success !== false,
    message: response?.message || "Banners fetched.",
    data: {
      ...emptyPage(grouped, 1, rows.length || 4),
      data: grouped,
    },
  };
}

export function normalizeFeaturedSections(
  response: any,
): PaginatedResponse<FeaturedSection[]> {
  return collectionToPaginated<FeaturedSection[]>(
    response,
    [],
    1,
    Array.isArray(response?.data) ? response.data.length || 20 : 20,
  );
}

export function normalizeFeaturedSectionProducts(
  response: any,
  page = 1,
  perPage = 20,
): PaginatedResponse<Product[]> {
  const section = response?.data;
  const products = Array.isArray(section?.products) ? section.products : [];

  return {
    success: response?.success !== false,
    message: response?.message || "",
    data: {
      ...emptyPage(products, page, perPage),
      data: products,
      total: section?.products_count ?? products.length,
    },
  };
}

export function normalizeProductFaqs(
  response: any,
  page = 1,
  perPage = 20,
): PaginatedResponse<ProductFaq[]> {
  return collectionToPaginated<ProductFaq[]>(response, [], page, perPage);
}

export function normalizeProductReviews(
  response: any,
): PaginatedResponse<ProductReviews> {
  const payload = response?.data || {};
  const reviews: Review[] = Array.isArray(payload.data) ? payload.data : [];
  const average = Number(payload.rating_summary?.average || 0);
  const total = Number(payload.rating_summary?.count ?? payload.total ?? 0);

  const reviewPayload: ProductReviews = {
    total_reviews: total,
    average_rating: average.toFixed(2),
    ratings_breakdown: {
      "1_star": String(payload.rating_summary?.breakdown?.["1"] || 0),
      "2_star": String(payload.rating_summary?.breakdown?.["2"] || 0),
      "3_star": String(payload.rating_summary?.breakdown?.["3"] || 0),
      "4_star": String(payload.rating_summary?.breakdown?.["4"] || 0),
      "5_star": String(payload.rating_summary?.breakdown?.["5"] || 0),
    },
    reviews: reviews.map((review: any) => ({
      ...review,
      product_id: review.product?.id ?? review.product_id ?? 0,
      slug: review.slug ?? String(review.id),
      review_images: review.review_images ?? [],
    })),
  };

  return {
    success: response?.success !== false,
    message: response?.message || "",
    data: {
      current_page: payload.current_page || 1,
      last_page: payload.last_page || 1,
      per_page: payload.per_page || 15,
      total,
      data: reviewPayload,
    },
  };
}
