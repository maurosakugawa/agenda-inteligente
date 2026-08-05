// src/components/ui/ThemeToggle.tsx
/**
 * Alternador de tema
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT
 * @version 1.0.0
 */
import { Moon, Sun } from "lucide-react";
import { useTheme } from "../../hooks/useTheme";

export default function ThemeToggle() {
  const { theme, toggleTheme } = useTheme();

  return (
    <button
      onClick={toggleTheme}
      className="btn btn-circle btn-ghost"
      aria-label={`Alternar para tema ${theme === 'dark' ? 'claro' : 'escuro'}`}
    >
      {theme === "light" ? (
        <Moon size={20} className="text-base-content" />
      ) : (
        <Sun size={20} className="text-base-content" />
      )}
    </button>
  );
}