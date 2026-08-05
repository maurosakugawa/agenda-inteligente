export interface WeatherTheme {
  bgGradient: string;
  textColor: string;
  cardBg: string;
  accentColor: string;
  particleType: 'none' | 'rain' | 'snow' | 'clouds';
}

export function getWeatherTheme(condition: string, isDay: boolean): WeatherTheme {
  const themes: Record<string, WeatherTheme> = {
    Clear: {
      bgGradient: isDay
        ? 'from-amber-400 via-orange-300 to-yellow-200'
        : 'from-slate-900 via-indigo-900 to-purple-900',
      textColor: isDay ? 'text-slate-800' : 'text-white',
      cardBg: isDay ? 'bg-white/30' : 'bg-white/10',
      accentColor: isDay ? 'amber-500' : 'indigo-400',
      particleType: 'none',
    },
    Rain: {
      bgGradient: 'from-slate-700 via-slate-600 to-slate-500',
      textColor: 'text-white',
      cardBg: 'bg-white/10',
      accentColor: 'blue-400',
      particleType: 'rain',
    },
    Drizzle: {
      bgGradient: 'from-slate-600 via-slate-500 to-slate-400',
      textColor: 'text-white',
      cardBg: 'bg-white/10',
      accentColor: 'blue-300',
      particleType: 'rain',
    },
    Clouds: {
      bgGradient: isDay
        ? 'from-gray-300 via-gray-200 to-slate-300'
        : 'from-slate-800 via-gray-800 to-slate-700',
      textColor: isDay ? 'text-slate-800' : 'text-white',
      cardBg: isDay ? 'bg-white/40' : 'bg-white/10',
      accentColor: 'gray-400',
      particleType: 'clouds',
    },
    Snow: {
      bgGradient: 'from-blue-100 via-white to-blue-50',
      textColor: 'text-slate-800',
      cardBg: 'bg-white/50',
      accentColor: 'blue-300',
      particleType: 'snow',
    },
    Thunderstorm: {
      bgGradient: 'from-slate-900 via-purple-900 to-slate-800',
      textColor: 'text-white',
      cardBg: 'bg-white/10',
      accentColor: 'purple-400',
      particleType: 'rain',
    },
  };

  return themes[condition] || themes.Clear;
}
