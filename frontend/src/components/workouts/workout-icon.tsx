const PATHS: Record<string, string[]> = {
  strength: [
    "m5 7 4-4m6 18 4-4M3 9l6-6m6 18 6-6M7 7l10 10M4 12l8-8m0 16 8-8",
  ],
  run: [
    "M15 4a2 2 0 1 0 0 .01",
    "m12 8-4 5 4 3-3 6m3-6 5 5m-5-13 4 4h5M3 10l5 1 4-3",
  ],
  time: ["M12 13a8 8 0 1 0 0-.01", "M12 9v5l3 2M9 2h6"],
  calendar: ["M3 5h18v16H3z", "M7 3v4m10-4v4M3 11h18"],
};

export function WorkoutIcon({
  name,
  className,
}: {
  name: "strength" | "run" | "time" | "calendar";
  className?: string;
}) {
  return (
    <svg
      className={className ? `workout-svg ${className}` : "workout-svg"}
      viewBox="0 0 24 24"
      aria-hidden="true"
      focusable="false"
    >
      {name === "run" ? (
        <>
          <circle cx="15" cy="4" r="2" />
          <path d={PATHS.run[1]} />
        </>
      ) : name === "time" ? (
        <>
          <circle cx="12" cy="13" r="8" />
          <path d="M12 9v5l3 2M9 2h6" />
        </>
      ) : name === "calendar" ? (
        <>
          <rect x="3" y="5" width="18" height="16" rx="3" />
          <path d="M7 3v4m10-4v4M3 11h18" />
        </>
      ) : (
        <path d={PATHS.strength[0]} />
      )}
    </svg>
  );
}

export { PATHS as WORKOUT_ICON_PATHS };
