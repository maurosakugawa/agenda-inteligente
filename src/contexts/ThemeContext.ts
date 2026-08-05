// src/contexts/ThemeContext.ts
/**
 * Context do tema da aplicação.
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT
 * @version 1.0.0
 */
import { createContext } from "react";

export type Theme = "light" | "dark";

export interface ThemeContextData {
  theme: Theme;
  toggleTheme: () => void;
}

export const ThemeContext = createContext<ThemeContextData | null>(null);