import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

export type SetupStep = 1 | 2 | 3;

const STEP_LABELS: Record<SetupStep, string> = {
  1: "Начнём знакомство",
  2: "Твои параметры и цель",
  3: "Всё готово",
};

export function StepProgress({
  step,
  label,
}: {
  step: SetupStep;
  label?: string;
}) {
  return (
    <div className="setup-progress">
      <span id="setup-step-label">{label ?? STEP_LABELS[step]}</span>
      <div
        role="progressbar"
        aria-labelledby="setup-step-label"
        aria-valuemin={1}
        aria-valuemax={3}
        aria-valuenow={step}
      >
        {[1, 2, 3].map((item) => (
          <i key={item} className={item <= step ? "on" : undefined} aria-hidden="true" />
        ))}
      </div>
    </div>
  );
}

function Story() {
  return (
    <div className="setup-story">
      <div className="logo">
        <span className="mark" aria-hidden="true">
          ∿
        </span>
        ритм
      </div>
      <div className="story-content">
        <div className="story-label">ПИТАНИЕ. ДВИЖЕНИЕ. ТЫ.</div>
        <h1>
          Твой ритм.
          <br />
          Твой баланс.
        </h1>
        <p>
          Не гнаться за идеалом.
          <br />А находить то, что подходит тебе.
        </p>
        <div className="story-art" aria-hidden="true">
          <div className="art-ring">
            <div>
              <b>∿</b>
              <span>В своём темпе</span>
            </div>
          </div>
          <span className="floating first">Питание</span>
          <span className="floating second">Активность</span>
          <span className="floating third">Твоя цель</span>
        </div>
      </div>
      <div className="story-footer">Маленькие шаги. Большие перемены.</div>
    </div>
  );
}

export function SetupShell({
  step,
  label,
  children,
  className,
}: {
  step: SetupStep;
  label?: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <section className="setup" aria-label="Настройка профиля">
      <Story />
      <div className={cn("setup-content", className)}>
        <StepProgress step={step} label={label} />
        {children}
      </div>
    </section>
  );
}
