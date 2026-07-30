export interface LocationAutoCompleteRef {
  setInputValue: (value: string) => void;
}

export interface MainText {
  text: string;
}

export interface SecondaryText {
  text: string;
}

export interface PlacePrediction {
  placeId: string;
  mainText: MainText | null;
  secondaryText: SecondaryText | null;
}

export interface AutocompleteSuggestionResult {
  placePrediction: PlacePrediction | null;
}

export interface FetchSuggestionsResponse {
  suggestions: AutocompleteSuggestionResult[];
}

export interface AutocompleteSuggestionRequest {
  input: string;
  sessionToken: google.maps.places.AutocompleteSessionToken;
  includedRegionCodes: string[];
}

export interface PredictionItem {
  key: string;
  label: string;
  description: string;
  original: PlacePrediction | null;
}

export type LocationSource = "device" | "search" | "map" | "saved";

export interface LocationSelection {
  placeName: string;
  latLng: { lat: number; lng: number };
  placeDescription: string;
  accuracyMeters?: number;
  source?: LocationSource;
}

export interface LocationAutoCompleteProps {
  onLocationSelect: (location: LocationSelection) => void;
}

export interface UserLocation {
  lat: number;
  lng: number;
  placeName: string;
  placeDescription: string;
  isDeliverable?: boolean;
  accuracyMeters?: number;
  source?: LocationSource;
}
