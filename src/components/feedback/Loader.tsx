interface LoaderProps {
  label?: string;
  fullHeight?: boolean;
}

export default function Loader({
  label = "Carregando...",
  fullHeight = false,
}: LoaderProps) {
  return (
    <div
      className={`flex flex-col items-center justify-center gap-3 ${
        fullHeight ? "min-h-screen" : "min-h-40"
      }`}
      role="status"
      aria-live="polite"
    >
      <span
        className="loading loading-spinner loading-lg text-primary"
        aria-hidden="true"
      />
      <span className="text-sm text-base-content/60">
        {label}
      </span>
    </div>
  );
}
