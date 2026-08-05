// src/modules/events/components/EventFilters.tsx
/**
 * Filtros dos eventos
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT
 * @version 1.0.0
 */

import {
  EVENT_CATEGORIES,
  EVENT_PRIORITIES,
} from "../types/event.types";

interface Props {
  category: string;
  setCategory: ( value: string ) => void;

  priority: string;
  setPriority: ( value: string ) => void;
}

export default function EventFilters({
  category,
  setCategory,
  priority,
  setPriority,
}: Props) {
  return (
    <div
      className="
        flex
        flex-col
        md:flex-row
        gap-4
      "
    >
      <select
        value={category}
        onChange={(e) =>
          setCategory(e.target.value)
        }
        className="
          select
          select-bordered
          rounded-xl
          w-full
          md:w-52
        "
      >
        <option value="">
          Todas categorias
        </option>

        {EVENT_CATEGORIES.map(
          (category) => (
            <option
              key={category}
              value={category}
            >
              {category}
            </option>
          )
        )}
      </select>

      <select
        value={priority}
        onChange={(e) =>
          setPriority(e.target.value)
        }
        className="
          select
          select-bordered
          rounded-xl
          w-full
          md:w-52
        "
      >
        <option value="">
          Todas prioridades
        </option>

        {EVENT_PRIORITIES.map(
          (priority) => (
            <option
              key={priority}
              value={priority}
            >
              {priority}
            </option>
          )
        )}
      </select>
    </div>
  );
}