/**
 * Tipagens do módulo de eventos.
 *
 * O frontend mantém seus nomes de domínio (date, time, reminder), enquanto
 * eventApi.service.ts converte esses campos para o contrato do backend.
 */

export const EVENT_PRIORITIES = [
  "Baixa",
  "Média",
  "Alta",
] as const;

export const EVENT_CATEGORIES = [
  "Geral",
  "Reunião",
  "Pessoal",
  "Trabalho",
  "Estudo",
  "Saúde",
  "Financeiro",
] as const;

export type EventPriority =
  (typeof EVENT_PRIORITIES)[number];

export type EventCategory =
  (typeof EVENT_CATEGORIES)[number];

export interface Event {
  id: string;
  title: string;
  description: string;
  date: string;
  time: string;

  category: EventCategory;
  priority: EventPriority;

  /**
   * Campo legado mantido temporariamente para compatibilidade com buscas
   * antigas. Novas associações devem usar contactIds.
   */
  contact?: string;

  location: string;
  contactIds: number[];

  reminder: number;
  createdAt: string;
  updatedAt?: string;

  /**
   * Estado somente do cliente nesta fase. O schema atual do backend ainda não
   * possui reminder_sent.
   */
  reminderSent?: boolean;
}

export type EventFormData = Event;
