import {
  createParticleStyle,
} from './particleLayout';

const RAIN_DROPS =
  Array.from(
    { length: 120 },
    (_, index) => ({
      id: index,
      style: createParticleStyle(
        index,
        2,
        3,
      ),
    }),
  );

export default function RainEffect() {
  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden">
      {RAIN_DROPS.map((drop) => (
        <span
          key={drop.id}
          className="
            absolute
            h-6
            w-[1px]
            animate-rain
            bg-white/30
          "
          style={drop.style}
        />
      ))}
    </div>
  );
}
