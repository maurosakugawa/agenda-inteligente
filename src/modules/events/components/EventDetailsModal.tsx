import {
  useEffect,
  useMemo,
  useState,
} from "react";

import {
  Calendar,
  Clock,
  Loader2,
  MapPin,
  Users,
  X,
} from "lucide-react";

import { Link } from "react-router-dom";

import type { Contact }
  from "../../contacts/types/contact.types";

import { useContactStore }
  from "../../contacts/store/useContactStore";

import { eventApiService }
  from "../services/eventApi.service";

import type { Event }
  from "../types/event.types";

import EventWeatherBadge
  from "./EventWeatherBadge";

interface Props {
  event: Event | null;
  onClose: () => void;
}

interface EventDetailsContentProps {
  event: Event;
  onClose: () => void;
}

export default function EventDetailsModal({
  event,
  onClose,
}: Props) {
  if (!event) {
    return null;
  }

  return (
    <EventDetailsContent
      key={event.id}
      event={event}
      onClose={onClose}
    />
  );
}

function EventDetailsContent({
  event,
  onClose,
}: EventDetailsContentProps) {
  const storedContacts = useContactStore(
    (state) => state.contacts
  );

  const [participants, setParticipants] =
    useState<Contact[]>([]);

  const [loading, setLoading] =
    useState(true);

  const fallbackParticipants =
    useMemo(
      () =>
        event.contactIds
          .map((id) =>
            storedContacts.find(
              (contact) =>
                contact.id === id
            )
          )
          .filter(
            (contact): contact is Contact =>
              contact !== undefined
          ),
      [event, storedContacts],
    );

  useEffect(() => {
    let active = true;

    void eventApiService
      .getContacts(event.id)
      .then((contacts) => {
        if (active) {
          setParticipants(contacts);
        }
      })
      .catch(() => {
        if (active) {
          setParticipants([]);
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false);
        }
      });

    return () => {
      active = false;
    };
  }, [event.id]);

  const displayedParticipants =
    participants.length
      ? participants
      : fallbackParticipants;

  return (
    <div
      className="modal modal-open"
      role="dialog"
      aria-modal="true"
      aria-labelledby="event-details-title"
    >
      <div className="modal-box max-w-2xl">
        <button
          type="button"
          onClick={onClose}
          className="btn btn-sm btn-circle btn-ghost absolute right-3 top-3"
          aria-label="Fechar detalhes"
        >
          <X size={18} />
        </button>

        <h2
          id="event-details-title"
          className="pr-10 text-2xl font-bold"
        >
          {event.title}
        </h2>

        {event.description && (
          <p className="mt-3 text-base-content/70">
            {event.description}
          </p>
        )}

        <div className="mt-6 grid gap-4 sm:grid-cols-2">
          <div className="flex items-center gap-3 rounded-box bg-base-200 p-4">
            <Calendar size={20} />
            <div>
              <span className="block text-xs text-base-content/60">
                Data
              </span>
              <strong>{event.date}</strong>
            </div>
          </div>

          <div className="flex items-center gap-3 rounded-box bg-base-200 p-4">
            <Clock size={20} />
            <div>
              <span className="block text-xs text-base-content/60">
                Horário
              </span>
              <strong>
                {event.time ||
                  "Sem horário"}
              </strong>
            </div>
          </div>
        </div>

        {event.location && (
          <section className="mt-6 rounded-box border border-base-300 p-4">
            <div className="mb-3 flex items-center gap-2 font-semibold">
              <MapPin size={18} />
              {event.location}
            </div>

            <EventWeatherBadge
              location={event.location}
              eventDate={event.date}
              eventTime={event.time}
            />

            <p className="mt-2 text-xs text-base-content/60">
              A API trabalha em intervalos de três horas. O badge informa o horário usado; datas fora da janela mostram previsão ainda indisponível.
            </p>
          </section>
        )}

        <section className="mt-6">
          <h3 className="mb-3 flex items-center gap-2 font-semibold">
            <Users size={18} />
            Participantes
          </h3>

          {loading ? (
            <div className="flex items-center gap-2 text-base-content/60">
              <Loader2
                size={18}
                className="animate-spin"
              />
              Carregando contatos...
            </div>
          ) : displayedParticipants.length ? (
            <div className="divide-y divide-base-300 rounded-box border border-base-300">
              {displayedParticipants.map(
                (contact) => (
                  <Link
                    key={contact.id}
                    to="/contacts"
                    className="block p-4 transition hover:bg-base-200"
                  >
                    <strong className="block">
                      {contact.name}
                    </strong>
                    <span className="text-sm text-base-content/60">
                      {contact.email ||
                        contact.phone ||
                        "Sem telefone/e-mail"}
                    </span>
                  </Link>
                )
              )}
            </div>
          ) : (
            <p className="text-sm text-base-content/60">
              Nenhum participante associado.
            </p>
          )}
        </section>

        <div className="modal-action">
          <button
            type="button"
            className="btn"
            onClick={onClose}
          >
            Fechar
          </button>
        </div>
      </div>

      <button
        type="button"
        className="modal-backdrop"
        onClick={onClose}
        aria-label="Fechar modal"
      >
        fechar
      </button>
    </div>
  );
}
