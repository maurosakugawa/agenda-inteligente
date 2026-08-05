// src/modules/events/components/EventSearch.tsx
/**
 * Campo de busca dos eventos
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT
 * @version 1.0.0
 */
import { Search } from "lucide-react";

interface Props {
  value: string;
  onChange: (value: string) => void;
}

export default function EventSearch({
  value,
  onChange,
}: Props) {
  return (
    <div className="relative">
      <Search
        size={18}
        className="
          absolute
          left-4
          top-4
          text-search-text/50
        "
      />
      <input
        type="text"
        placeholder="Buscar eventos..."
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="
          input
          input-bordered
          w-full
          pl-12
          h-14
          rounded-2xl
          bg-search-bg
          text-search-text
          placeholder:text-search-text/50
          border-base-300
          focus:outline-none
          focus:ring-2
          focus:ring-primary/50
          transition-colors
          duration-300
        "
      />
    </div>
  );
}