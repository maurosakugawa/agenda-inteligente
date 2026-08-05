// src/themes/weatherAnimations.ts
// Central de animações para os temas de clima

export const weatherAnimations = {

  clear: {
    background: "animate-gradient-slow",
    card: "hover:scale-[1.01]",
    icon: "animate-pulse",
  },

  clouds: {
    background: "animate-cloud-drift",
    card: "hover:translate-y-[-2px]",
    icon: "animate-bounce",
  },

  rain: {
    background: "animate-rain",
    card: "backdrop-blur-xl",
    icon: "animate-pulse",
  },

  drizzle: {
    background: "animate-drizzle",
    card: "opacity-95",
    icon: "animate-pulse",
  },

  thunderstorm: {
    background: "animate-lightning",
    card: "shadow-violet-500/20",
    icon: "animate-bounce",
  },

  snow: {
    background: "animate-snow",
    card: "backdrop-blur-2xl",
    icon: "animate-pulse",
  },

  default: {
    background: "animate-fade-in",
    card: "transition-all duration-700",
    icon: "",
  },
};