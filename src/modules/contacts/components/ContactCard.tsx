import { Phone, Mail, MapPin, Pencil, Trash2 } from 'lucide-react';
import type { Contact } from '../types/contact.types';

interface Props {
  contact: Contact;
  onEdit: (contact: Contact) => void;
  onDelete: (id: number) => void;
  deleting: boolean;
}

export default function ContactCard({ contact, onEdit, onDelete, deleting }: Props) {
  return (
    <div className="card bg-base-100 shadow-md hover:shadow-lg transition-shadow">
      <div className="card-body p-4">
        <div className="flex justify-between items-start">
          <div>
            <h3 className="font-bold text-lg">{contact.name}</h3>
            <div className="flex flex-col gap-1 mt-2 text-sm text-gray-500">
              {contact.phone && (
                <span className="flex items-center gap-1">
                  <Phone className="w-4 h-4" /> {contact.phone}
                </span>
              )}
              {contact.email && (
                <span className="flex items-center gap-1">
                  <Mail className="w-4 h-4" /> {contact.email}
                </span>
              )}
              {(contact.logradouro || contact.cidade) && (
                <span className="flex items-center gap-1">
                  <MapPin className="w-4 h-4" />
                  {contact.logradouro}{contact.numero ? `, ${contact.numero}` : ''}
                  {contact.bairro ? ` - ${contact.bairro}` : ''}
                  {contact.cidade ? `, ${contact.cidade}` : ''}
                  {contact.uf ? `/${contact.uf}` : ''}
                </span>
              )}
            </div>
          </div>
          <div className="flex gap-1">
            <button
              onClick={() => onEdit(contact)}
              className="btn btn-ghost btn-sm btn-circle"
              title="Editar"
            >
              <Pencil className="w-4 h-4" />
            </button>
            <button
              onClick={() => onDelete(contact.id)}
              className="btn btn-ghost btn-sm btn-circle text-error"
              title="Excluir"
              disabled={deleting}
            >
              {deleting ? <span className="loading loading-spinner loading-xs" /> : <Trash2 className="w-4 h-4" />}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
