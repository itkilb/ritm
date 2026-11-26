import { apiRequest } from "@/lib/api";

export type WeightEntry = {
  id: string;
  date: string;
  weight_kg: number;
};

export type WeightList = {
  data: WeightEntry[];
  meta: { timezone: string; today: string };
};

export async function fetchWeightEntries(
  from?: string | null,
  to?: string | null,
): Promise<WeightList> {
  const params = new URLSearchParams();

  if (from) {
    params.set("from", from);
  }

  if (to) {
    params.set("to", to);
  }

  return apiRequest<WeightList>(`/v1/weight-entries?${params.toString()}`);
}

export async function createWeightEntry(input: {
  date: string;
  weight_kg: number;
}): Promise<WeightEntry> {
  const payload = await apiRequest<{ data: WeightEntry }>("/v1/weight-entries", {
    method: "POST",
    body: JSON.stringify(input),
  });

  return payload.data;
}

export async function updateWeightEntry(
  id: string,
  input: { date: string; weight_kg: number },
): Promise<WeightEntry> {
  const payload = await apiRequest<{ data: WeightEntry }>(
    `/v1/weight-entries/${encodeURIComponent(id)}`,
    {
      method: "PUT",
      body: JSON.stringify(input),
    },
  );

  return payload.data;
}

export async function deleteWeightEntry(id: string): Promise<void> {
  await apiRequest<void>(`/v1/weight-entries/${encodeURIComponent(id)}`, {
    method: "DELETE",
  });
}

export async function updateTargetWeight(
  targetWeightKg: number | null,
): Promise<number | null> {
  const payload = await apiRequest<{ data: { target_weight_kg: number | null } }>(
    "/v1/me/target-weight",
    {
      method: "PUT",
      body: JSON.stringify({ target_weight_kg: targetWeightKg }),
    },
  );

  return payload.data.target_weight_kg;
}
