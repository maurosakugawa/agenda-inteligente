import {
  useEffect,
  useState,
} from 'react';

const DEFAULT_INTERVAL_MS =
  60_000;

const INITIAL_CURRENT_TIME =
  Date.now();

export function useCurrentTime(
  intervalMs = DEFAULT_INTERVAL_MS,
): number {
  const [currentTime, setCurrentTime] =
    useState(INITIAL_CURRENT_TIME);

  useEffect(() => {
    const intervalId =
      window.setInterval(() => {
        setCurrentTime(Date.now());
      }, intervalMs);

    return () => {
      window.clearInterval(intervalId);
    };
  }, [intervalMs]);

  return currentTime;
}
