// src/modules/events/components/EventSort.tsx

/**
 * Ordenação dos eventos
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT
 * @version 1.0.0
 */

interface Props {
  value: string;

  onChange: (
    value: string
  ) => void;
}

export default function EventSort({
  value,
  onChange,
}: Props) {
  return (
    <select
      value={value}
      onChange={(e) =>
        onChange(e.target.value)
      }
      className="
        select
        select-bordered
        rounded-2xl
        bg-base-100
        w-full
        md:w-60
        p-2
      "
    >
      <option value="date">
        Mais próximos primeiro
      </option>

      <option value="priority">
        Prioridade
      </option>

      <option value="title">
        Ordem alfabética
      </option>
    </select>
  );
}