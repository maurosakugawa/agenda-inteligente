/**
 * Card integrado de evento.
 */

import { useState } from "react";

import {
  Calendar,
  Clock,
  MapPin,
  Pencil,
  Trash2,
} from "lucide-react";

import { motion } from "framer-motion";

import type {
  Event,
  EventPriority,
} from "../types/event.types";

import { useEventModal }
  from "../store/useEventModal";

import { useNotifications }
  from "../../notifications/hooks/useNotifications";

import Badge
  from "../../../components/ui/Badge";

import Card
  from "../../../components/ui/Card";

import ConfirmDialog
  from "../../../components/ui/ConfirmDialog";

import { slideUp }
  from "../../../animations/slide";

import EventWeatherBadge
  from "./EventWeatherBadge";

import EventParticipants
  from "./EventParticipants";

interface EventCardProps {
  event: Event;
  onDelete: (id: string) => Promise<void>;
}

export default function EventCard({
  event,
  onDelete,
}: EventCardProps) {
  const openEdit = useEventModal(
    (state) => state.openEdit
  );

  const notifications =
    useNotifications();

  const [confirmOpen, setConfirmOpen] =
    useState(false);

  const [deleting, setDeleting] =
    useState(false);

  const priorityVariant: Record<
    EventPriority,
    "low" | "medium" | "high"
  > = {
    Baixa: "low",
    Média: "medium",
    Alta: "high",
  };

  const confirmDelete = async () => {
    setDeleting(true);

    try {
      await onDelete(event.id);

      notifications.warning(
        "Evento removido",
        "O evento foi excluído do servidor."
      );

      setConfirmOpen(false);
    } catch (error) {
      notifications.error(
        "Não foi possível excluir",
        error instanceof Error
          ? error.message
          : "Tente novamente."
      );
    } finally {
      setDeleting(false);
    }
  };

  return (
    <>
      <motion.div
        variants={slideUp}
        whileHover={{
          y: -4,
          scale: 1.01,
        }}
        transition={{ duration: 0.2 }}
      >
        <Card className="transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-primary/10">
          <div className="flex items-start justify-between gap-4">
            <div className="min-w-0">
              <h3 className="truncate text-lg font-bold text-base-content">
                {event.title}
              </h3>

              {event.description && (
                <p className="mt-1 line-clamp-3 text-base-content/60">
                  {event.description}
                </p>
              )}
            </div>

            <Badge
              variant={
                priorityVariant[event.priority]
              }
            >
              {event.priority}
            </Badge>
          </div>

          <div className="mt-6 space-y-3 text-sm text-base-content/70">
            <div className="flex items-center gap-2">
              <Calendar size={16} />
              {event.date}
            </div>

            <div className="flex items-center gap-2">
              <Clock size={16} />
              {event.time || "Sem horário"}
            </div>

            {event.location && (
              <div className="flex items-center gap-2">
                <MapPin size={16} />
                <span className="truncate">
                  {event.location}
                </span>
              </div>
            )}
          </div>

          <div className="mt-5 flex flex-wrap items-center gap-3">
            <EventWeatherBadge
              location={event.location}
              eventDate={event.date}
              eventTime={event.time}
            />

            <span className="badge badge-outline">
              {event.category}
            </span>
          </div>

          {event.contactIds.length > 0 && (
            <div className="mt-5 border-t border-base-300 pt-4">
              <EventParticipants
                contactIds={event.contactIds}
              />
            </div>
          )}

          <div className="mt-6 flex justify-end gap-2">
            <button
              type="button"
              onClick={() => openEdit(event)}
              className="btn btn-sm btn-outline"
              aria-label={`Editar ${event.title}`}
            >
              <Pencil size={16} />
            </button>

            <button
              type="button"
              onClick={() =>
                setConfirmOpen(true)
              }
              className="btn btn-sm btn-error"
              aria-label={`Excluir ${event.title}`}
            >
              <Trash2 size={16} />
            </button>
          </div>
        </Card>
      </motion.div>

      <ConfirmDialog
        isOpen={confirmOpen}
        title="Excluir evento"
        message={
          deleting
            ? "Excluindo evento..."
            : "Tem certeza que deseja excluir este evento?"
        }
        onConfirm={() => {
          void confirmDelete();
        }}
        onCancel={() => {
          if (!deleting) {
            setConfirmOpen(false);
          }
        }}
      />
    </>
  );
}
