import { useEffect, useId, useRef, type ReactNode } from "react";
import { createPortal } from "react-dom";

import { cn } from "@/lib/utils";

const FOCUSABLE_SELECTOR = [
  "a[href]",
  "button:not([disabled])",
  "input:not([disabled])",
  "select:not([disabled])",
  "textarea:not([disabled])",
  "[tabindex]:not([tabindex='-1'])",
].join(",");

function focusableElements(panel: HTMLElement): HTMLElement[] {
  return Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)).filter(
    (element) =>
      element.getAttribute("type") !== "hidden" &&
      element.getAttribute("aria-hidden") !== "true" &&
      !element.closest("[hidden]"),
  );
}

export function Modal({
  title,
  onClose,
  closeDisabled = false,
  describedBy,
  headerAction,
  children,
  className,
}: {
  title: string;
  onClose: () => void;
  closeDisabled?: boolean;
  describedBy?: string;
  headerAction?: ReactNode;
  children: ReactNode;
  className?: string;
}) {
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const onCloseRef = useRef(onClose);
  const closeDisabledRef = useRef(closeDisabled);

  onCloseRef.current = onClose;
  closeDisabledRef.current = closeDisabled;

  useEffect(() => {
    const panel = panelRef.current;
    const previouslyFocused =
      document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;
    const previousOverflow = document.body.style.overflow;

    document.body.classList.add("modal-open");
    document.body.style.overflow = "hidden";

    const initial =
      panel?.querySelector<HTMLElement>("[data-autofocus]") ??
      (panel ? focusableElements(panel)[0] : null);
    initial?.focus();

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") {
        if (closeDisabledRef.current) {
          event.preventDefault();
          return;
        }

        event.preventDefault();
        onCloseRef.current();
        return;
      }

      if (event.key !== "Tab" || !panel) {
        return;
      }

      const focusable = focusableElements(panel);

      if (focusable.length === 0) {
        event.preventDefault();
        panel.focus();
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      const active = document.activeElement;

      if (event.shiftKey) {
        if (active === first || !panel.contains(active)) {
          event.preventDefault();
          last.focus();
        }
        return;
      }

      if (active === last || !panel.contains(active)) {
        event.preventDefault();
        first.focus();
      }
    }

    document.addEventListener("keydown", handleKeyDown);

    return () => {
      document.removeEventListener("keydown", handleKeyDown);
      document.body.classList.remove("modal-open");
      document.body.style.overflow = previousOverflow;

      if (previouslyFocused?.isConnected) {
        previouslyFocused.focus();
      }
    };
  }, []);

  return createPortal(
    <div className="modal-layer">
      <div
        className="modal-backdrop"
        aria-hidden="true"
        onMouseDown={(event) => {
          if (
            event.target === event.currentTarget &&
            !closeDisabledRef.current
          ) {
            onCloseRef.current();
          }
        }}
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={describedBy}
        tabIndex={-1}
        className={cn("modal-panel", className)}
      >
        {headerAction ? (
          <div className="modal-heading">
            <h2 id={titleId} className="modal-title">
              {title}
            </h2>
            {headerAction}
          </div>
        ) : (
          <h2 id={titleId} className="modal-title">
            {title}
          </h2>
        )}
        {children}
      </div>
    </div>,
    document.body,
  );
}
