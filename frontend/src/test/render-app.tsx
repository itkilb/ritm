import {
  QueryClient,
  QueryClientProvider,
  type QueryKey,
} from "@tanstack/react-query";
import { render } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";

import App from "@/App";
import type { SessionUser } from "@/hooks/use-session";
import { startOfIsoWeek } from "@/lib/date";
import { useUiStore } from "@/stores/use-ui-store";
import { dashboardFixture, weeklyFixture } from "@/test/fixtures";

type RenderAppOptions = {
  user: SessionUser | null;
  route?: string;
  extraQueries?: Array<[QueryKey, unknown]>;
  seedSession?: boolean;
  selectedDate?: string | null;
  seedDashboard?: boolean;
};

export function createTestQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });
}

export function renderApp({
  user,
  route = "/",
  extraQueries = [],
  seedSession = true,
  selectedDate = null,
  seedDashboard = true,
}: RenderAppOptions) {
  const queryClient = createTestQueryClient();

  useUiStore.getState().reset();

  if (selectedDate !== null) {
    useUiStore.getState().setSelectedDate(selectedDate);
  }

  if (seedSession) {
    queryClient.setQueryData(["session"], user);
  }

  for (const [key, data] of extraQueries) {
    queryClient.setQueryData(key, data);
  }

  if (!extraQueries.some(([key]) => key[0] === "goals")) {
    queryClient.setQueryData(["goals", "current"], null);
  }

  if (seedDashboard) {
    if (!extraQueries.some(([key]) => key[0] === "dashboard")) {
      queryClient.setQueryData(
        ["dashboard", selectedDate ?? "today"],
        dashboardFixture({ date: selectedDate ?? undefined }),
      );
    }

    if (!extraQueries.some(([key]) => key[0] === "weekly")) {
      queryClient.setQueryData(
        ["weekly", selectedDate ? startOfIsoWeek(selectedDate) : "current"],
        weeklyFixture(
          selectedDate ? { start: startOfIsoWeek(selectedDate) } : {},
        ),
      );
    }
  }

  const view = render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[route]}>
        <App />
      </MemoryRouter>
    </QueryClientProvider>,
  );

  return { ...view, queryClient };
}
