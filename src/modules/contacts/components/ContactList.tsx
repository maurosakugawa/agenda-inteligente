import { Search, Users, Plus } from 'lucide-react';
import { useState } from 'react';
import ContactCard from './ContactCard';
import type { Contact } from '../types/contact.types';

interface Props {
  contacts: Contact[];
  onEdit: (contact: Contact) => void;
  onDelete: (id: number) => void;
  onAdd: () => void;
  deletingId: number | null;
}

export default function ContactList({ contacts, onEdit, onDelete, onAdd, deletingId }: Props) {
  const [search, setSearch] = useState('');

  const filtered = contacts.filter((c) =>
    c.name.toLowerCase().includes(search.toLowerCase()) ||
    c.cidade?.toLowerCase().includes(search.toLowerCase()) ||
    c.phone?.includes(search)
  );

  return (
    <div className="space-y-4">
      <div className="flex flex-col sm:flex-row gap-3 justify-between items-center">
        <div className="relative w-full sm:w-96">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            type="text"
            placeholder="Buscar contato..."
            className="input input-bordered w-full pl-10"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        <button onClick={onAdd} className="btn btn-primary">
          <Plus className="w-5 h-5" /> Novo Contato
        </button>
      </div>

      {filtered.length === 0 ? (
        <div className="text-center py-16">
          <Users className="w-16 h-16 mx-auto text-gray-300 mb-4" />
          <p className="text-lg text-gray-500">
            {search ? 'Nenhum contato encontrado' : 'Nenhum contato cadastrado'}
          </p>
          {!search && (
            <button onClick={onAdd} className="btn btn-outline mt-4">
              <Plus className="w-4 h-4" /> Adicionar primeiro contato
            </button>
          )}
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filtered.map((contact) => (
            <ContactCard
              key={contact.id}
              contact={contact}
              onEdit={onEdit}
              onDelete={onDelete}
              deleting={deletingId === contact.id}
            />
          ))}
        </div>
      )}
    </div>
  );
}
