// src/components/effects/AuroraEffect.tsx
export default function AuroraEffect() {

  return (
    <div
      className="
        absolute inset-0
        bg-gradient-to-r
        from-cyan-500/10
        via-fuchsia-500/10
        to-indigo-500/10
        animate-gradient-slow
        blur-3xl
        pointer-events-none
      "
    />
  );
}