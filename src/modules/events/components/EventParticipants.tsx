import { Users } from "lucide-react";

import { useContactStore }
  from "../../contacts/store/useContactStore";

interface Props {
  contactIds: number[];
  maxVisible?: number;
  showNames?: boolean;
}

function getInitials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("")
    .toUpperCase();
}

export default function EventParticipants({
  contactIds,
  maxVisible = 4,
  showNames = true,
}: Props) {
  const contacts = useContactStore(
    (state) => state.contacts
  );

  if (!contactIds.length) {
    return null;
  }

  const participants =
    contactIds
      .map((id) =>
        contacts.find(
          (contact) => contact.id === id
        )
      )
      .filter(
        (contact) => contact !== undefined
      );

  if (!participants.length) {
    return (
      <div className="flex items-center gap-2 text-sm text-base-content/60">
        <Users size={16} />
        {contactIds.length} participante(s)
      </div>
    );
  }

  const visible =
    participants.slice(0, maxVisible);

  const hiddenCount =
    participants.length - visible.length;

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="flex -space-x-2">
        {visible.map((contact) => (
          <div
            key={contact.id}
            className="avatar placeholder"
            title={contact.name}
          >
            <div className="w-8 rounded-full bg-primary text-primary-content ring-2 ring-base-100">
              <span className="text-xs">
                {getInitials(contact.name)}
              </span>
            </div>
          </div>
        ))}

        {hiddenCount > 0 && (
          <div className="avatar placeholder">
            <div className="w-8 rounded-full bg-base-300 text-base-content ring-2 ring-base-100">
              <span className="text-xs">
                +{hiddenCount}
              </span>
            </div>
          </div>
        )}
      </div>

      {showNames && (
        <span className="text-sm text-base-content/70">
          {visible
            .map((contact) => contact.name)
            .join(", ")}
          {hiddenCount > 0
            ? ` e mais ${hiddenCount}`
            : ""}
        </span>
      )}
    </div>
  );
}
