// src/layouts/Header.tsx
/**
 * Header principal da aplicação
 *
 * Responsável por exibir:
 * - título da página
 * - descrição
 * - busca global
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @version 1.0.0
 * @license MIT License
 */
import { Search } from "lucide-react";
import ThemeToggle from "../components/ui/ThemeToggle";

interface Props {
  title: string;
  subtitle: string;
}

export function Header({
  title,
  subtitle,
}: Props) {
  return (
    <header
      className="
        h-24
        border-b
        border-base-300
        bg-header-bg
        px-8
        flex
        items-center
        justify-between
        transition-colors
        duration-300
      "
    >
      <div>
        <h1 className="text-3xl font-bold text-base-content">
          {title}
        </h1>
        <p className="text-base-content">
          {subtitle}
        </p>
      </div>

      <div className="flex items-center gap-4">
        {/* Busca */}
        <label
          className="
            input
            input-bordered
            flex
            items-center
            gap-2
            rounded-2xl
            w-72
            bg-search-bg
            border-base-300
          "
        >
          <Search
            size={18}
            className="text-search-text/60"
          />
          <input
            type="text"
            className="
              grow
              bg-transparent
              text-search-text
              placeholder:text-search-text/50
            "
            placeholder="Buscar eventos..."
          />
        </label>

        {/* Toggle */}
        <ThemeToggle />

        {/* Avatar
        <div className="avatar placeholder">
          <div className="bg-primary text-neutral-content rounded-full w-10">
            <span className="text-xs">M</span>
          </div>
        </div>
         */}
      </div>
    </header>
  );
}