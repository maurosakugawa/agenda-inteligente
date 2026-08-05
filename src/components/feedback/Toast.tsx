import { useEffect } from "react";

interface ToastProps {
  message: string;
  show: boolean;
  onClose: () => void;
  type?: "info" | "success" | "warning" | "error";
  duration?: number;
}

const alertClasses: Record<
  NonNullable<ToastProps["type"]>,
  string
> = {
  info: "alert-info",
  success: "alert-success",
  warning: "alert-warning",
  error: "alert-error",
};

export default function Toast({
  message,
  show,
  onClose,
  type = "info",
  duration = 4000,
}: ToastProps) {
  useEffect(() => {
    if (!show || duration <= 0) {
      return;
    }

    const timeoutId = window.setTimeout(
      onClose,
      duration
    );

    return () => {
      window.clearTimeout(timeoutId);
    };
  }, [duration, onClose, show]);

  if (!show) {
    return null;
  }

  return (
    <div className="toast toast-top toast-end z-50">
      <div
        className={`alert ${alertClasses[type]} shadow-lg`}
        role="alert"
      >
        <span>{message}</span>

        <button
          type="button"
          className="btn btn-ghost btn-sm btn-circle"
          onClick={onClose}
          aria-label="Fechar mensagem"
        >
          ×
        </button>
      </div>
    </div>
  );
}
