import { useState, useEffect } from 'react';
import { Users } from 'lucide-react';
import { useContactStore } from '../store/useContactStore';
import ContactList from '../components/ContactList';
import ContactForm from '../components/ContactForm';
import type { Contact, ContactInput } from '../types/contact.types';

export default function ContactsPage() {
  const { contacts, loading, error, fetchContacts, createContact, updateContact, deleteContact, clearError } = useContactStore();
  const [showForm, setShowForm] = useState(false);
  const [editingContact, setEditingContact] = useState<Contact | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  useEffect(() => {
    fetchContacts();
  }, [fetchContacts]);

  const handleSave = async (data: ContactInput) => {
    try {
      if (editingContact) {
        await updateContact(editingContact.id, data);
      } else {
        await createContact(data);
      }
      setShowForm(false);
      setEditingContact(null);
    } catch {
      // erro já está na store
    }
  };

  const handleEdit = (contact: Contact) => {
    setEditingContact(contact);
    setShowForm(true);
  };

  const handleDelete = async (id: number) => {
    setDeletingId(id);
    try {
      await deleteContact(id);
    } finally {
      setDeletingId(null);
    }
  };

  const handleCancel = () => {
    setShowForm(false);
    setEditingContact(null);
    clearError();
  };

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="flex items-center gap-3 mb-6">
        <Users className="w-8 h-8" />
        <h1 className="text-3xl font-bold">Contatos</h1>
      </div>

      {error && (
        <div className="alert alert-error mb-4">
          <span>{error}</span>
          <button onClick={clearError} className="btn btn-ghost btn-sm">×</button>
        </div>
      )}

      {showForm ? (
        <ContactForm
          key={
            editingContact
              ? `edit-${editingContact.id}`
              : 'new-contact'
          }
          contact={editingContact}
          onSave={handleSave}
          onCancel={handleCancel}
          loading={loading}
        />
      ) : (
        <ContactList
          contacts={contacts}
          onEdit={handleEdit}
          onDelete={handleDelete}
          onAdd={() => setShowForm(true)}
          deletingId={deletingId}
        />
      )}
    </div>
  );
}
