/**
 * Formulário de criação e edição de eventos.
 */

import {
  useEffect,
  useState,
} from "react";

import {
  CheckCheck,
  Loader2,
  MapPin,
  Search,
  Users,
  XCircle,
} from "lucide-react";

import Button
  from "../../../components/ui/Button";

import { useNotifications }
  from "../../notifications/hooks/useNotifications";

import { useContactStore }
  from "../../contacts/store/useContactStore";

import { useEventStore }
  from "../store/useEventStore";

import { useEventModal }
  from "../store/useEventModal";

import {
  EVENT_CATEGORIES,
  EVENT_PRIORITIES,
  type Event,
  type EventCategory,
  type EventPriority,
} from "../types/event.types";

function createEmptyForm(): Event {
  return {
    id: "",
    title: "",
    description: "",
    date: "",
    time: "",
    category: "Pessoal",
    priority: "Média",
    contact: "",
    location: "",
    contactIds: [],
    reminder: 30,
    createdAt: "",
  };
}

export default function EventForm() {
  const addEvent = useEventStore(
    (state) => state.addEvent
  );

  const updateEvent = useEventStore(
    (state) => state.updateEvent
  );

  const close = useEventModal(
    (state) => state.close
  );

  const editingEvent = useEventModal(
    (state) => state.editingEvent
  );

  const contacts = useContactStore(
    (state) => state.contacts
  );

  const contactsLoaded = useContactStore(
    (state) => state.loaded
  );

  const contactsLoading = useContactStore(
    (state) => state.loading
  );

  const fetchContacts = useContactStore(
    (state) => state.fetchContacts
  );

  const notifications = useNotifications();

  const [submitting, setSubmitting] =
    useState(false);

  const [contactSearch, setContactSearch] =
    useState("");

  const [formData, setFormData] =
    useState<Event>(() => {
      if (!editingEvent) {
        return createEmptyForm();
      }

      return {
        ...editingEvent,
        contactIds:
          editingEvent.contactIds || [],
      };
    });

  useEffect(() => {
    if (!contactsLoaded && !contactsLoading) {
      void fetchContacts();
    }
  }, [
    contactsLoaded,
    contactsLoading,
    fetchContacts,
  ]);

  const handleChange = (
    event: React.ChangeEvent<
      | HTMLInputElement
      | HTMLTextAreaElement
      | HTMLSelectElement
    >
  ) => {
    const { name, value } = event.target;

    setFormData((current) => ({
      ...current,
      [name]:
        name === "reminder"
          ? Number(value)
          : value,
    }));
  };

  const toggleContact = (
    contactId: number
  ) => {
    setFormData((current) => {
      const selected =
        current.contactIds.includes(
          contactId
        );

      return {
        ...current,
        contactIds: selected
          ? current.contactIds.filter(
              (id) => id !== contactId
            )
          : [
              ...current.contactIds,
              contactId,
            ],
      };
    });
  };


  const normalizedContactSearch =
    contactSearch.trim().toLocaleLowerCase(
      "pt-BR"
    );

  const filteredContacts = contacts.filter(
    (contact) => {
      if (!normalizedContactSearch) {
        return true;
      }

      return [
        contact.name,
        contact.email,
        contact.phone,
      ]
        .filter(Boolean)
        .join(" ")
        .toLocaleLowerCase("pt-BR")
        .includes(normalizedContactSearch);
    }
  );

  const visibleContactIds =
    filteredContacts.map(
      (contact) => contact.id
    );

  const allVisibleSelected =
    visibleContactIds.length > 0 &&
    visibleContactIds.every((id) =>
      formData.contactIds.includes(id)
    );

  const selectVisibleContacts = () => {
    setFormData((current) => ({
      ...current,
      contactIds: Array.from(
        new Set([
          ...current.contactIds,
          ...visibleContactIds,
        ])
      ),
    }));
  };

  const clearContactSelection = () => {
    setFormData((current) => ({
      ...current,
      contactIds: [],
    }));
  };

  const handleSubmit = async (
    event: React.FormEvent
  ) => {
    event.preventDefault();

    setSubmitting(true);

    try {
      if (editingEvent) {
        await updateEvent(formData);

        notifications.info(
          "Evento atualizado",
          "As alterações foram persistidas no servidor."
        );
      } else {
        await addEvent({
          ...formData,
          id: "",
          createdAt:
            new Date().toISOString(),
        });

        notifications.success(
          "Evento criado",
          "O evento foi persistido no servidor."
        );
      }

      close();
    } catch (error) {
      notifications.error(
        "Não foi possível salvar",
        error instanceof Error
          ? error.message
          : "Tente novamente."
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form
      onSubmit={handleSubmit}
      className="space-y-6"
    >
      <fieldset
        className="fieldset"
        disabled={submitting}
      >
        <legend className="fieldset-legend">
          Informações do evento
        </legend>

        <label className="fieldset-label" htmlFor="event-title">
          Título
        </label>
        <input
          id="event-title"
          type="text"
          name="title"
          placeholder="Ex.: Reunião de planejamento"
          value={formData.title}
          onChange={handleChange}
          className="input input-bordered w-full"
          required
          maxLength={200}
        />

        <label className="fieldset-label" htmlFor="event-description">
          Descrição
        </label>
        <textarea
          id="event-description"
          name="description"
          placeholder="Detalhes importantes do compromisso"
          value={formData.description}
          onChange={handleChange}
          className="textarea textarea-bordered min-h-28 w-full"
        />

        <div className="grid gap-5 md:grid-cols-2">
          <div>
            <label className="fieldset-label" htmlFor="event-date">
              Data
            </label>
            <input
              id="event-date"
              type="date"
              name="date"
              value={formData.date}
              onChange={handleChange}
              className="input input-bordered w-full"
              required
            />
          </div>

          <div>
            <label className="fieldset-label" htmlFor="event-time">
              Horário
            </label>
            <input
              id="event-time"
              type="time"
              name="time"
              value={formData.time}
              onChange={handleChange}
              className="input input-bordered w-full"
              required
            />
          </div>
        </div>

        <div className="grid gap-5 md:grid-cols-2">
          <div>
            <label className="fieldset-label" htmlFor="event-category">
              Categoria
            </label>
            <select
              id="event-category"
              name="category"
              value={formData.category}
              onChange={handleChange}
              className="select select-bordered w-full"
            >
              {EVENT_CATEGORIES.map(
                (
                  category: EventCategory
                ) => (
                  <option
                    key={category}
                    value={category}
                  >
                    {category}
                  </option>
                )
              )}
            </select>
          </div>

          <div>
            <label className="fieldset-label" htmlFor="event-priority">
              Prioridade
            </label>
            <select
              id="event-priority"
              name="priority"
              value={formData.priority}
              onChange={handleChange}
              className="select select-bordered w-full"
            >
              {EVENT_PRIORITIES.map(
                (
                  priority: EventPriority
                ) => (
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
        </div>
      </fieldset>

      <fieldset className="fieldset">
        <legend className="fieldset-legend flex items-center gap-2">
          <MapPin size={16} />
          Local e clima
        </legend>

        <label className="fieldset-label" htmlFor="event-location">
          Cidade do evento
        </label>
        <input
          id="event-location"
          type="text"
          name="location"
          placeholder="Ex.: São Paulo"
          value={formData.location}
          onChange={handleChange}
          className="input input-bordered w-full"
          maxLength={100}
        />
        <p className="fieldset-label">
          Informe preferencialmente a cidade; ela será usada na consulta à OpenWeather.
        </p>
      </fieldset>

      <fieldset className="fieldset">
        <legend className="fieldset-legend flex items-center gap-2">
          <Users size={16} />
          Participantes
        </legend>

        <p className="mb-2 text-sm text-base-content/60">
          Selecione quantos contatos forem necessários. Há {contacts.length} contato(s) disponível(is).
        </p>

        {contactsLoading && !contactsLoaded ? (
          <div className="flex items-center gap-2 rounded-box bg-base-200 p-4 text-base-content/70">
            <Loader2
              size={18}
              className="animate-spin"
            />
            Carregando contatos...
          </div>
        ) : contacts.length ? (
          <div className="space-y-3">
            <div className="flex flex-col gap-2 sm:flex-row">
              <label className="input input-bordered flex flex-1 items-center gap-2">
                <Search
                  size={17}
                  className="text-base-content/50"
                />
                <input
                  type="search"
                  className="grow"
                  placeholder="Buscar participante..."
                  value={contactSearch}
                  onChange={(event) =>
                    setContactSearch(
                      event.target.value
                    )
                  }
                />
              </label>

              <div className="flex gap-2">
                <button
                  type="button"
                  className="btn btn-outline btn-sm flex-1 sm:flex-none"
                  onClick={selectVisibleContacts}
                  disabled={
                    !visibleContactIds.length ||
                    allVisibleSelected
                  }
                >
                  <CheckCheck size={16} />
                  Selecionar visíveis
                </button>

                <button
                  type="button"
                  className="btn btn-ghost btn-sm flex-1 sm:flex-none"
                  onClick={clearContactSelection}
                  disabled={
                    !formData.contactIds.length
                  }
                >
                  <XCircle size={16} />
                  Limpar
                </button>
              </div>
            </div>

            <div
              className="max-h-56 space-y-2 overflow-y-auto rounded-box border border-base-300 p-3"
              role="group"
              aria-label="Selecionar participantes"
            >
              {filteredContacts.length ? (
                filteredContacts.map(
                  (contact) => {
                    const checked =
                      formData.contactIds.includes(
                        contact.id
                      );

                    return (
                      <label
                        key={contact.id}
                        className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition ${
                          checked
                            ? "border-primary bg-primary/10"
                            : "border-transparent hover:bg-base-200"
                        }`}
                      >
                        <input
                          type="checkbox"
                          className="checkbox checkbox-primary"
                          checked={checked}
                          onChange={() =>
                            toggleContact(
                              contact.id
                            )
                          }
                        />

                        <span className="min-w-0">
                          <strong className="block truncate">
                            {contact.name}
                          </strong>
                          <span className="block truncate text-sm text-base-content/60">
                            {contact.email ||
                              contact.phone ||
                              "Sem telefone/e-mail"}
                          </span>
                        </span>
                      </label>
                    );
                  }
                )
              ) : (
                <div className="p-4 text-center text-sm text-base-content/60">
                  Nenhum contato encontrado para essa busca.
                </div>
              )}
            </div>
          </div>
        ) : (
          <div className="rounded-box bg-base-200 p-4 text-sm text-base-content/70">
            Cadastre contatos antes de associar participantes ao evento.
          </div>
        )}

        <p className="fieldset-label">
          {formData.contactIds.length} contato(s) selecionado(s) de {contacts.length}.
        </p>
      </fieldset>

      <fieldset className="fieldset">
        <legend className="fieldset-legend">
          Lembrete
        </legend>

        <select
          name="reminder"
          value={formData.reminder}
          onChange={handleChange}
          className="select select-bordered w-full"
        >
          <option value={0}>
            Sem lembrete
          </option>
          <option value={5}>
            5 minutos antes
          </option>
          <option value={10}>
            10 minutos antes
          </option>
          <option value={15}>
            15 minutos antes
          </option>
          <option value={30}>
            30 minutos antes
          </option>
          <option value={60}>
            1 hora antes
          </option>
          <option value={1440}>
            1 dia antes
          </option>
        </select>
      </fieldset>

      <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <button
          type="button"
          onClick={close}
          className="btn btn-ghost"
          disabled={submitting}
        >
          Cancelar
        </button>

        <Button
          type="submit"
          disabled={submitting}
        >
          {submitting && (
            <Loader2
              size={16}
              className="animate-spin"
            />
          )}

          {editingEvent
            ? "Salvar alterações"
            : "Criar evento"}
        </Button>
      </div>
    </form>
  );
}
