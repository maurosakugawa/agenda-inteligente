import {
  useCallback,
  useState,
} from 'react';

import type {
  ChangeEvent,
} from 'react';

export function usePhoneMask(
  initialValue = '',
) {
  const [value, setValue] =
    useState(initialValue);

  const onChange = useCallback(
    (
      event: ChangeEvent<HTMLInputElement>,
    ) => {
      let nextValue =
        event.target.value.replace(/\D/g, '');

      if (nextValue.length > 11) {
        nextValue =
          nextValue.slice(0, 11);
      }



      if (nextValue.length > 10) {
        nextValue =
          `(${nextValue.slice(0, 2)}) `
          + `${nextValue.slice(2, 7)}-`
          + nextValue.slice(7);
      } else if (nextValue.length > 6) {
        nextValue =
          `(${nextValue.slice(0, 2)}) `
          + `${nextValue.slice(2, 6)}-`
          + nextValue.slice(6);
      } else if (nextValue.length > 2) {
        nextValue =
          `(${nextValue.slice(0, 2)}) `
          + nextValue.slice(2);
      } else if (nextValue.length > 0) {
        nextValue =
          `(${nextValue}`;
      }

      setValue(nextValue);
    },
    [],
  );



  const setPhone = useCallback(
    (phone: string) => {
      setValue(phone);
    },
    [],
  );

  return {
    value,
    onChange,
    setPhone,
  };
}
