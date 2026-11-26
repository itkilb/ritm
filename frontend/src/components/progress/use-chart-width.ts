import { useLayoutEffect, useRef, useState } from "react";

export const DEFAULT_CHART_WIDTH = 720;

export function useChartWidth<T extends HTMLElement>() {
  const containerRef = useRef<T>(null);
  const [width, setWidth] = useState(DEFAULT_CHART_WIDTH);

  useLayoutEffect(() => {
    const node = containerRef.current;

    if (!node) {
      return;
    }

    const apply = (next: number) => {
      if (Number.isFinite(next) && next > 0) {
        setWidth(Math.round(next));
      }
    };

    const measure = () => apply(node.getBoundingClientRect().width);

    measure();

    if (typeof ResizeObserver === "undefined") {
      window.addEventListener("resize", measure);

      return () => window.removeEventListener("resize", measure);
    }

    const observer = new ResizeObserver((entries) => {
      const next = entries[0]?.contentRect.width;

      if (typeof next === "number") {
        apply(next);
      } else {
        measure();
      }
    });

    observer.observe(node);

    return () => observer.disconnect();
  }, []);

  return { containerRef, width };
}
