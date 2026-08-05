import { apiUrl } from "../../../config/api";

import type { Contact } from "../../contacts/types/contact.types";
import {
  EVENT_CATEGORIES,
  EVENT_PRIORITIES,
  type Event,
  type EventCategory,
  type EventPriority,
} from "../types/event.types";


interface ApiEvent {
  id: number | string;
  user_id?: number;
  title: string;
  description?: string | null;
  event_date: string;
  event_time?: string | null;
  category?: string | null;
  priority?: string | null;
  location?: string | null;
  contact_ids?: number[] | string | null;
  reminder_minutes?: number | string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

interface ApiEventPayload {
  title: string;
  description: string;
  event_date: string;
  event_time: string | null;
  category: string;
  priority: string;
  location: string | null;
  contact_ids: number[];
  reminder_minutes: number;
}

class ApiRequestError extends Error {
  status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = "ApiRequestError";
    this.status = status;
  }
}

async function requestJson<T>(
  path: string,
  options: RequestInit = {}
): Promise<T> {
  const response = await fetch(apiUrl(path), {
    ...options,
    credentials: "include",
    headers: {
      "Content-Type": "application/json",
      ...options.headers,
    },
  });

  const body = await response
    .json()
    .catch(() => null) as
      | { error?: string; message?: string }
      | T
      | null;

  if (!response.ok) {
    const errorBody =
      body && typeof body === "object"
        ? body as { error?: string; message?: string }
        : null;

    throw new ApiRequestError(
      errorBody?.error ||
        errorBody?.message ||
        "Erro ao comunicar com o servidor.",
      response.status
    );
  }

  return body as T;
}

function normalizeKey(value: string): string {
  return value
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .trim()
    .toLowerCase();
}

function normalizePriority(
  value?: string | null
): EventPriority {
  const key = normalizeKey(value || "");

  const aliases: Record<string, EventPriority> = {
    baixa: "Baixa",
    low: "Baixa",
    media: "Média",
    medium: "Média",
    alta: "Alta",
    high: "Alta",
  };

  return aliases[key] || "Média";
}

function normalizeCategory(
  value?: string | null
): EventCategory {
  const key = normalizeKey(value || "");

  const found = EVENT_CATEGORIES.find(
    (category) => normalizeKey(category) === key
  );

  return found || "Geral";
}

function parseContactIds(
  value: ApiEvent["contact_ids"]
): number[] {
  if (Array.isArray(value)) {
    return value
      .map(Number)
      .filter(Number.isInteger);
  }

  if (typeof value === "string" && value.trim()) {
    try {
      const parsed = JSON.parse(value);

      if (Array.isArray(parsed)) {
        return parsed
          .map(Number)
          .filter(Number.isInteger);
      }
    } catch {
      return [];
    }
  }

  return [];
}

function normalizeTime(
  value?: string | null
): string {
  if (!value) {
    return "";
  }

  return value.slice(0, 5);
}

function normalizeDate(value: string): string {
  return value.slice(0, 10);
}

function toDomainEvent(apiEvent: ApiEvent): Event {
  return {
    id: String(apiEvent.id),
    title: apiEvent.title,
    description: apiEvent.description || "",
    date: normalizeDate(apiEvent.event_date),
    time: normalizeTime(apiEvent.event_time),
    category: normalizeCategory(apiEvent.category),
    priority: normalizePriority(apiEvent.priority),
    location: apiEvent.location || "",
    contactIds: parseContactIds(apiEvent.contact_ids),
    reminder: Number(apiEvent.reminder_minutes || 0),
    createdAt:
      apiEvent.created_at ||
      new Date().toISOString(),
    updatedAt:
      apiEvent.updated_at || undefined,
    reminderSent: false,
  };
}

function toApiPayload(event: Event): ApiEventPayload {
  const priority =
    EVENT_PRIORITIES.includes(event.priority)
      ? event.priority
      : "Média";

  const category =
    EVENT_CATEGORIES.includes(event.category)
      ? event.category
      : "Geral";

  return {
    title: event.title.trim(),
    description: event.description.trim(),
    event_date: event.date,
    event_time: event.time || null,
    category,
    priority,
    location: event.location.trim() || null,
    contact_ids: event.contactIds || [],
    reminder_minutes:
      Number.isFinite(event.reminder)
        ? event.reminder
        : 0,
  };
}

export const eventApiService = {
  async list(): Promise<Event[]> {
    const events =
      await requestJson<ApiEvent[]>("/api/events");

    return events.map(toDomainEvent);
  },

  async create(event: Event): Promise<Event> {
    const created =
      await requestJson<ApiEvent>("/api/events", {
        method: "POST",
        body: JSON.stringify(toApiPayload(event)),
      });

    return toDomainEvent(created);
  },

  async update(event: Event): Promise<Event> {
    const updated =
      await requestJson<ApiEvent>(
        `/api/events/${encodeURIComponent(event.id)}`,
        {
          method: "PUT",
          body: JSON.stringify(toApiPayload(event)),
        }
      );

    return toDomainEvent(updated);
  },

  async remove(id: string): Promise<void> {
    await requestJson<{ message: string }>(
      `/api/events/${encodeURIComponent(id)}`,
      { method: "DELETE" }
    );
  },

  async getContacts(
    eventId: string
  ): Promise<Contact[]> {
    return requestJson<Contact[]>(
      `/api/events/${encodeURIComponent(eventId)}/contacts`
    );
  },
};

export { ApiRequestError };
