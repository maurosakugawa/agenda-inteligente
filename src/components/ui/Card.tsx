// src/components/ui/Card.tsx
/**
 * Card component for rendering styled cards
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT License
 * @version 1.0.0
 */
interface CardProps {
  children: React.ReactNode;
  className?: string;
}

export default function Card({ children, className = "" }: CardProps) {
  return (
    <div className={`bg-card-bg border border-base-300 rounded-3xl p-6 shadow-lg transition-colors duration-300 ${className}`}>
      {children}
    </div>
  );
}