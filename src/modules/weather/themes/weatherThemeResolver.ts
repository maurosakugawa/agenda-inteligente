import {
  getWeatherTheme,
} from './weatherTheme';

import {
  weatherIcons,
} from './weatherIcons';

import {
  weatherAnimations,
} from './weatherAnimations';

import {
  weatherParticles,
} from './weatherParticles';

import {
  weatherBackgrounds,
} from './weatherBackgrounds';

import type {
  ResolvedWeatherTheme,
} from './weatherEffects.types';

import type {
  WeatherType,
} from './weather.types';



function normalizeWeather(
  weather?: string,
): WeatherType {
  const normalized =
    weather?.toLowerCase();

  switch (normalized) {
    case 'clear':
    case 'clouds':
    case 'rain':
    case 'drizzle':
    case 'thunderstorm':
    case 'snow':
      return normalized;

    default:
      return 'default';
  }
}

const weatherThemeNames:
  Record<WeatherType, string> = {
    clear: 'Clear',
    clouds: 'Clouds',
    rain: 'Rain',
    drizzle: 'Drizzle',
    thunderstorm: 'Thunderstorm',
    snow: 'Snow',
    default: 'Clear',
  };



const weatherIconNames:
  Record<
    WeatherType,
    keyof typeof weatherIcons
  > = {
    clear: 'Clear',
    clouds: 'Clouds',
    rain: 'Rain',
    drizzle: 'Drizzle',
    thunderstorm: 'Thunderstorm',
    snow: 'Snow',
    default: 'Clear',
  };

export function resolveWeatherTheme(
  weather?: string,
  isNight = false,
): ResolvedWeatherTheme {
  const normalized =
    normalizeWeather(weather);

  const iconName =
    isNight
    && normalized === 'clear'
      ? 'Night'
      : weatherIconNames[normalized];


  return {
    theme: getWeatherTheme(
      weatherThemeNames[normalized],
      !isNight,
    ),

    animation:
      weatherAnimations[normalized],

    icon:
      weatherIcons[iconName],

    particle:
      weatherParticles[normalized],

    background:
      weatherBackgrounds[normalized],
  };
}
