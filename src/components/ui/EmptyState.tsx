// src/components/ui/EmptyState.tsx
/**
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT License
 * @version 1.0.0
 */
import { CalendarDays } from "lucide-react";

export default function EmptyState() {
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <CalendarDays size={64} className="text-base-content/30" />
      <h3 className="mt-4 text-xl font-semiboldtext-base-content " >
        Nenhum evento cadastrado
      </h3>
      <p className="text-base-content/50 mt-2">
        Crie seu primeiro compromisso.
      </p>
    </div>
  );
}