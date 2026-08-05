import {
  createParticleStyle,
} from './particleLayout';

const SNOWFLAKES =
  Array.from(
    { length: 80 },
    (_, index) => ({
      id: index,
      style: createParticleStyle(
        index,
        4,
        7,
      ),
    }),
  );

export default function SnowEffect() {
  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden">
      {SNOWFLAKES.map((snowflake) => (
        <span
          key={snowflake.id}
          className="
            absolute
            h-2
            w-2
            animate-snow
            rounded-full
            bg-white/70
          "
          style={snowflake.style}
        />
      ))}
    </div>
  );
}
