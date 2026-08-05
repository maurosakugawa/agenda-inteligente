// src/components/ui/Badge.tsx
/**
 *
 * @author Mauro Sakugawa
 * Date: 2026-05-21
 * License: MIT License
 * @version 1.0.0
 */
interface BadgeProps {
  children: React.ReactNode;
  variant?: "low" | "medium" | "high";
}

export default function Badge({
  children,
  variant = "low",
}: BadgeProps) {
  const variants = {
    low: "bg-green-100 text-green-700",
    medium: "bg-yellow-100 text-yellow-700",
    high: "bg-red-100 text-red-700",
  };

  return (
    <span
      className={`
        px-3
        py-1
        rounded-full
        text-xs
        font-semibold
        ${variants[variant]}
      `}
    >
      {children}
    </span>
  );
}