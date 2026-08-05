// src/components/ui/Input.tsx
/**
 * Componente padrão de input da aplicação
 *
 * Centraliza:
 * - estilo
 * - dark mode
 * - tamanhos
 * - consistência visual
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT
 * @version 1.0.0
 */
import type { InputHTMLAttributes } from "react";

type InputProps = InputHTMLAttributes<HTMLInputElement>;

export default function Input({ className = "", ...props }: InputProps) {
  return (
    <input
      className={`
        input
        input-bordered
        bg-search-bg
        text-search-text
        placeholder:text-search-text/50
        h-12
        rounded-2xl
        text-base
        w-full
        transition-colors duration-300
        focus:outline-none
        focus:ring-2
        focus:ring-primary/50
        ${className}
      `}
      {...props}
    />
  );
}