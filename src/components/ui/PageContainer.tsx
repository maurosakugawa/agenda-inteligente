// src/components/ui/PageContainer.tsx
/**
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT License
 * @version 1.0.0
 */
interface Props {
  children: React.ReactNode;
}

export default function PageContainer({ children }: Props) {
  return (
    <div className="p-8 max-w-7xl mx-auto w-full">
      {children}
    </div>
  );
}