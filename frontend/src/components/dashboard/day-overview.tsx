import { formatIsoDate } from "@/lib/date";

export function DayOverview({
  date,
  today,
  onAddRecord,
}: {
  date: string | null;
  today: string | null;
  onAddRecord: () => void;
}) {
  const isToday = date !== null && today !== null && date === today;
  const heading = date && !isToday ? formatIsoDate(date) : "Обзор дня";

  return (
    <div className="intro">
      <div>
        <h2>{heading}</h2>
        <div className="muted">Всё важное — в одном месте</div>
      </div>
      <button
        type="button"
        className="primary"
        onClick={onAddRecord}
        disabled={!date}
      >
        ＋ Добавить запись
      </button>
    </div>
  );
}
