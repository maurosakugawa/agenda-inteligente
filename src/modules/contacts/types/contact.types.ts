export interface Contact {
  id: number;
  user_id: number;
  name: string;
  phone: string;
  email: string;
  cep: string;
  logradouro: string;
  numero: string;
  bairro: string;
  cidade: string;
  uf: string;
  created_at: string;
  updated_at: string;
}

export interface ContactInput {
  name: string;
  phone: string;
  email: string;
  cep: string;
  logradouro: string;
  numero: string;
  bairro: string;
  cidade: string;
  uf: string;
}
