import { useState } from 'react';
import { X, Save } from 'lucide-react';
import { useCEP } from '../hooks/useCEP';
import { usePhoneMask } from '../hooks/usePhoneMask';
import type { Contact, ContactInput } from '../types/contact.types';

interface Props {
  contact?: Contact | null;
  onSave: (data: ContactInput) => void;
  onCancel: () => void;
  loading: boolean;
}

function createInitialForm(
  contact?: Contact | null,
): ContactInput {
  if (!contact) {
    return {
      name: '',
      phone: '',
      email: '',
      cep: '',
      logradouro: '',
      numero: '',
      bairro: '',
      cidade: '',
      uf: '',
    };
  }

  return {
    name: contact.name,
    phone: contact.phone,
    email: contact.email,
    cep: contact.cep,
    logradouro: contact.logradouro,
    numero: contact.numero,
    bairro: contact.bairro,
    cidade: contact.cidade,
    uf: contact.uf,
  };
}

export default function ContactForm({ contact, onSave, onCancel, loading }: Props) {
  const [form, setForm] =
    useState<ContactInput>(
      () => createInitialForm(contact),
    );

  const {
    fetchAddress,
    loading: cepLoading,
  } = useCEP();

  const phoneMask =
    usePhoneMask(contact?.phone ?? '');

  const handleChange = (field: keyof ContactInput, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const handleCEPBlur = async () => {
    const cleanCEP = form.cep.replace(/\D/g, '');
    if (cleanCEP.length === 8) {
      const address = await fetchAddress(cleanCEP);
      if (address) {
        setForm((prev) => ({
          ...prev,
          logradouro: address.logradouro,
          bairro: address.bairro,
          cidade: address.cidade,
          uf: address.uf,
        }));
      }
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSave({ ...form, phone: phoneMask.value });
  };

  return (
    <div className="card bg-base-100 shadow-xl max-w-4xl mx-auto">
      <div className="card-body">
        <div className="flex justify-between items-center mb-6">
          <h3 className="card-title text-xl">{contact ? 'Editar Contato' : 'Novo Contato'}</h3>
          <button onClick={onCancel} className="btn btn-ghost btn-sm btn-circle">
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">

            {/* Nome */}
            <fieldset className="fieldset min-w-0">
              <legend className="fieldset-legend">
                Nome *
              </legend>

              <input
                id="contact-name"
                type="text"
                className="input input-bordered w-full"
                value={form.name}
                onChange={(e) => handleChange('name', e.target.value)}
                required
              />
            </fieldset>

            {/* Telefone */}
            <fieldset className="fieldset min-w-0">
              <legend className="fieldset-legend">
                Telefone
              </legend>

              <input
                id="contact-phone"
                type="tel"
                className="input input-bordered w-full"
                value={phoneMask.value}
                onChange={phoneMask.onChange}
                placeholder="(11) 99999-9999"
                inputMode="tel"
              />
            </fieldset>

            {/* E-mail */}
            <fieldset className="fieldset min-w-0">
              <legend className="fieldset-legend">
                E-mail
              </legend>

              <input
                id="contact-email"
                type="email"
                className="input input-bordered w-full"
                value={form.email}
                onChange={(e) => handleChange('email', e.target.value)}
                autoComplete="email"
              />
            </fieldset>

            {/* CEP */}
            <fieldset className="fieldset min-w-0">
              <legend className="fieldset-legend">
                CEP
              </legend>

              <div className="flex w-full items-center gap-2">
                <input
                  id="contact-cep"
                  type="text"
                  className="input input-bordered min-w-0 flex-1"
                  value={form.cep}
                  onChange={(e) => handleChange('cep', e.target.value)}
                  onBlur={handleCEPBlur}
                  placeholder="00000-000"
                  inputMode="numeric"
                  maxLength={9}
                />

                {cepLoading && (
                  <span
                    className="loading loading-spinner loading-sm shrink-0"
                    aria-label="Consultando CEP"
                  />
                )}
              </div>
            </fieldset>

            {/* Logradouro */}
            <fieldset className="fieldset min-w-0 md:col-span-2">
              <legend className="fieldset-legend">
                Logradouro
              </legend>

              <input
                id="contact-logradouro"
                type="text"
                className="input input-bordered w-full"
                value={form.logradouro}
                onChange={(e) => handleChange('logradouro', e.target.value)}
              />
            </fieldset>

            {/* Número */}
            <fieldset className="fieldset min-w-0">
              <legend className="fieldset-legend">
                Número
              </legend>

              <input
                id="contact-numero"
                type="text"
                className="input input-bordered w-full"
                value={form.numero}
                onChange={(e) => handleChange('numero', e.target.value)}
              />
            </fieldset>

            {/* Bairro */}
            <fieldset className="fieldset min-w-0">
              <legend className="fieldset-legend">
                Bairro
              </legend>

              <input
                id="contact-bairro"
                type="text"
                className="input input-bordered w-full"
                value={form.bairro}
                onChange={(e) => handleChange('bairro', e.target.value)}
              />
            </fieldset>

            {/* Cidade */}
            <fieldset className="fieldset min-w-0">
              <legend className="fieldset-legend">
                Cidade
              </legend>

              <input
                id="contact-cidade"
                type="text"
                className="input input-bordered w-full"
                value={form.cidade}
                onChange={(e) => handleChange('cidade', e.target.value)}
              />
            </fieldset>

            {/* UF */}
            <fieldset className="fieldset min-w-0">
              <legend className="fieldset-legend">
                UF
              </legend>

              <input
                id="contact-uf"
                type="text"
                className="input input-bordered w-24 self-start uppercase"
                value={form.uf}
                onChange={(e) =>
                  handleChange(
                    'uf',
                    e.target.value
                      .replace(/[^a-zA-Z]/g, '')
                      .toUpperCase()
                      .slice(0, 2),
                  )
                }
                placeholder="SP"
                maxLength={2}
              />
            </fieldset>
          </div>

          <div className="card-actions mt-6 justify-end border-t border-base-200 pt-4">
            <button
              type="button"
              onClick={onCancel}
              className="btn btn-ghost"
              disabled={loading}
            >
              Cancelar
            </button>

            <button
              type="submit"
              className="btn btn-primary"
              disabled={loading || cepLoading}
            >
              {loading ? (
                <span className="loading loading-spinner loading-sm" />
              ) : (
                <Save className="h-4 w-4" />
              )}

              Salvar
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
