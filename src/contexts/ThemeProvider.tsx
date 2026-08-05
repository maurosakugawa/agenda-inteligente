// src/contexts/ThemeProvider.tsx

/**
 * Provider responsável pelo gerenciamento
 * do tema global da aplicação.
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT
 * @version 1.0.0
 */
import {
  useEffect,
  useState,
  type ReactNode,
} from "react";
import { ThemeContext } from "./ThemeContext";

interface Props {
  children: ReactNode;
}

type Theme = "light" | "dark";

export default function ThemeProvider({
  children,
}: Props) {
  // Inicializa do localStorage ou sistema
  const [theme, setTheme] = useState<Theme>(() => {
    if (typeof window !== 'undefined') {
      const saved = localStorage.getItem("theme") as Theme;
      if (saved) return saved;

      // Detecta preferência do sistema
      if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return "dark";
      }
    }
    return "light";
  });

  // Aplica o tema no HTML e localStorage
  useEffect(() => {
    const root = window.document.documentElement;
    root.setAttribute("data-theme", theme);
    localStorage.setItem("theme", theme);
  }, [theme]);

  /**
   * Alterna entre light e dark.
   */
  const toggleTheme = () => {
    setTheme((prev) => (prev === "dark" ? "light" : "dark"));
  };

  return (
    <ThemeContext.Provider value={{ theme, toggleTheme }}>
      {children}
    </ThemeContext.Provider>
  );
}