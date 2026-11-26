import { create } from "zustand";

type UiState = {
  selectedDate: string | null;
  setSelectedDate: (date: string | null) => void;
  reset: () => void;
};

export const useUiStore = create<UiState>((set) => ({
  // null follows the server's today in the profile timezone; the browser clock is UTC.
  selectedDate: null,
  setSelectedDate: (selectedDate) => set({ selectedDate }),
  reset: () => set({ selectedDate: null }),
}));
