import {
  useEffect,
  type ReactNode,
} from "react";

interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  children: ReactNode;
}

export default function Modal({
  isOpen,
  onClose,
  title,
  children,
}: ModalProps) {
  useEffect(() => {
    if (!isOpen) {
      return;
    }

    const previousOverflow =
      document.body.style.overflow;

    document.body.style.overflow = "hidden";

    const handleKeyDown = (
      event: KeyboardEvent
    ) => {
      if (event.key === "Escape") {
        onClose();
      }
    };

    window.addEventListener(
      "keydown",
      handleKeyDown
    );

    return () => {
      document.body.style.overflow =
        previousOverflow;

      window.removeEventListener(
        "keydown",
        handleKeyDown
      );
    };
  }, [isOpen, onClose]);

  if (!isOpen) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-50 overflow-y-auto bg-black/40 p-3 backdrop-blur-sm sm:p-6"
      role="presentation"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) {
          onClose();
        }
      }}
    >
      <div className="flex min-h-full items-start justify-center sm:items-center">
        <section
          className="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-2xl flex-col overflow-hidden rounded-3xl bg-base-100 text-base-content shadow-2xl sm:max-h-[calc(100dvh-3rem)]"
          role="dialog"
          aria-modal="true"
          aria-labelledby="modal-title"
          onMouseDown={(event) => {
            event.stopPropagation();
          }}
        >
          <header className="flex shrink-0 items-center justify-between border-b border-base-300 px-5 py-4 sm:px-6 sm:py-5">
            <h2
              id="modal-title"
              className="text-xl font-bold"
            >
              {title}
            </h2>

            <button
              type="button"
              onClick={onClose}
              className="btn btn-ghost btn-sm btn-circle"
              aria-label="Fechar modal"
            >
              ✕
            </button>
          </header>

          <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5 pb-10 sm:px-6 sm:py-6 sm:pb-12">
            {children}
          </div>
        </section>
      </div>
    </div>
  );
}
