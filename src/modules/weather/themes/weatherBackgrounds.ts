// src/themes/weatherBackgrounds.ts
import type { WeatherBackground } from "./weatherEffects.types";

export const weatherBackgrounds: Record<
  string,
  WeatherBackground
> = {

  clear: {
    overlay:
      "bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.15),_transparent_60%)]",

    blend: "mix-blend-screen",
  },

  clouds: {
    overlay:
      "bg-[radial-gradient(circle,_rgba(255,255,255,0.08),_transparent_70%)]",

    blend: "mix-blend-soft-light",
  },

  rain: {
    overlay:
      "bg-[linear-gradient(to_bottom,_rgba(255,255,255,0.03),_transparent)]",

    blend: "mix-blend-overlay",
  },

  thunderstorm: {
    overlay:
      "bg-[radial-gradient(circle,_rgba(139,92,246,0.15),_transparent_70%)]",

    blend: "mix-blend-screen",
  },

  snow: {
    overlay:
      "bg-[radial-gradient(circle,_rgba(255,255,255,0.2),_transparent_70%)]",

    blend: "mix-blend-lighten",
  },

  default: {
    overlay: "",
    blend: "",
  },
};