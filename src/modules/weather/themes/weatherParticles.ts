// src/themes/weatherParticles .ts
import type { WeatherParticleConfig } from "./weatherEffects.types";

export const weatherParticles: Record<
  string,
  WeatherParticleConfig
> = {

  clear: {
    type: "aurora",
    density: 10,
    speed: 1,
    opacity: 0.3,
  },

  clouds: {
    type: "clouds",
    density: 25,
    speed: 0.5,
    opacity: 0.2,
  },

  rain: {
    type: "rain",
    density: 120,
    speed: 1.8,
    opacity: 0.4,
  },

  drizzle: {
    type: "rain",
    density: 60,
    speed: 1,
    opacity: 0.25,
  },

  thunderstorm: {
    type: "lightning",
    density: 180,
    speed: 2.5,
    opacity: 0.6,
  },

  snow: {
    type: "snow",
    density: 80,
    speed: 0.4,
    opacity: 0.8,
  },

  default: {
    type: "none",
    density: 0,
    speed: 0,
    opacity: 0,
  },
};