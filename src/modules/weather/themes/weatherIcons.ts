import {
  Cloud,
  CloudLightning,
  CloudRain,
  Moon,
  Snowflake,
  Sun,
} from 'lucide-react';

import type {
  LucideIcon,
} from 'lucide-react';

type WeatherIconName =
  | 'Clear'
  | 'Clouds'
  | 'Rain'
  | 'Drizzle'
  | 'Thunderstorm'
  | 'Snow'
  | 'Night';

export const weatherIcons:
  Record<WeatherIconName, LucideIcon> = {
    Clear: Sun,
    Clouds: Cloud,
    Rain: CloudRain,
    Drizzle: CloudRain,
    Thunderstorm: CloudLightning,
    Snow: Snowflake,
    Night: Moon,
  };
