import type {
  CSSProperties,
} from 'react';

/**
 * Produz uma fração determinística entre 0 e 1.
 *
 * O resultado depende somente do índice e da semente,
 * permanecendo estável entre renderizações.
 */
function deterministicFraction(
  index: number,
  seed: number,
): number {
  const value =
    ((index + 1) * (seed * 37 + 17))
    % 997;

  return value / 997;
}

export function createParticleStyle(
  index: number,
  maximumDelay: number,
  seed: number,
): CSSProperties {
  const left =
    deterministicFraction(index, seed)
    * 100;

  const animationDelay =
    deterministicFraction(index, seed + 11)
    * maximumDelay;

  return {
    left: `${left}%`,
    animationDelay: `${animationDelay}s`,
  };
}
