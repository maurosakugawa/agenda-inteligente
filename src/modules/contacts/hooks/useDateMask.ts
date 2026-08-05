import { useState, useCallback } from 'react';

export function useDateMask() {
  const [value, setValue] = useState('');

  const onChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 8) v = v.slice(0, 8);

    if (v.length > 4) {
      v = `${v.slice(0, 2)}/${v.slice(2, 4)}/${v.slice(4)}`;
    } else if (v.length > 2) {
      v = `${v.slice(0, 2)}/${v.slice(2)}`;
    }

    setValue(v);
  }, []);

  const setDate = useCallback((date: string) => {
    setValue(date);
  }, []);

  return { value, onChange, setDate };
}
