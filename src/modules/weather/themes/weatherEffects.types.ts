import type {
  LucideIcon,
} from 'lucide-react';

import type {
  WeatherTheme,
} from './weatherTheme';

export interface WeatherAnimation {
  background: string;
  card: string;
  icon: string;
}

export interface WeatherParticleConfig {
  type:
    | 'rain'
    | 'snow'
    | 'clouds'
    | 'lightning'
    | 'aurora'
    | 'none';

  density: number;
  speed: number;
  opacity: number;
}

export interface WeatherBackground {
  overlay: string;
  blend: string;
}

export interface ResolvedWeatherTheme {
  theme: WeatherTheme;

  animation: WeatherAnimation;

  particle: WeatherParticleConfig;

  background: WeatherBackground;

  icon: LucideIcon;
}
