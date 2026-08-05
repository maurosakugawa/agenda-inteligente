import type { ReactNode } from "react";

interface PageContainerProps {
  children: ReactNode;
  className?: string;
}

export default function PageContainer({
  children,
  className = "",
}: PageContainerProps) {
  return (
    <div
      className={`container mx-auto w-full px-4 py-6 ${className}`}
    >
      {children}
    </div>
  );
}
