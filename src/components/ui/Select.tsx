// src/componets/ui/Select.tsx
/**
 *
 * @author Mauro Sakugawa
 * @created 2026-05-22
 * @license MIT
 * @version 1.0.0
 */
type Props =
  React.SelectHTMLAttributes<HTMLSelectElement>;

export default function Select({
  className = "",
  children,
  ...props
}: Props) {
  return (
    <select
      className={`
        select
        glass
        rounded-2xl
        text-white
        w-full
        ${className}
      `}
      {...props}
    >
      {children}
    </select>
  );
}