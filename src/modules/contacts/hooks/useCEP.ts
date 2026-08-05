import { useState, useCallback } from 'react';

export interface AddressData {
  logradouro: string;
  bairro: string;
  cidade: string;
  uf: string;
}

export function useCEP() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const fetchAddress = useCallback(async (cep: string): Promise<AddressData | null> => {
    const cleanCEP = cep.replace(/\D/g, '');
    if (cleanCEP.length !== 8) {
      setError('CEP inválido');
      return null;
    }

    setLoading(true);
    setError('');

    try {
      const res = await fetch(`https://viacep.com.br/ws/${cleanCEP}/json/`);
      const data = await res.json();

      if (data.erro) {
        setError('CEP não encontrado');
        return null;
      }

      return {
        logradouro: data.logradouro,
        bairro: data.bairro,
        cidade: data.localidade,
        uf: data.uf,
      };
    } catch {
      setError('Erro ao buscar CEP');
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  return { fetchAddress, loading, error };
}
