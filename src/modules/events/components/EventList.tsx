// src/modules/events/components/EventList.tsx

/**
 * Lista de eventos
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT
 * @version 1.0.0
 */

import type {
  Event,
} from "../types/event.types";

import { useEventStore } from "../store/useEventStore";
import { motion } from "framer-motion";
import EventCard from "./EventCard";

import EmptyState from "../../../components/ui/EmptyState";

interface Props {
  events: Event[];
}

export default function EventList({
  events,
}: Props) {

  const removeEvent = useEventStore(
    (state) => state.removeEvent
  );

  const container = {
    hidden: {},

    visible: {
      transition: {
        staggerChildren: 0.08,
      },
    },
  };

  if (!events.length) {
    return <EmptyState />;
  }

  return (
    <motion.div
      variants={container}
      initial="hidden"
      animate="visible"
      className="
        grid
        grid-cols-1
        xl:grid-cols-2
        gap-6
      "
    >
      {events.map((event) => (
        <EventCard
          key={event.id}
          event={event}
          onDelete={removeEvent}
        />
      ))}
    </motion.div>
  );
}