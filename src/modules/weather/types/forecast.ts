// src/types/forecast.js

export interface ForecastItem {
    dt: number;
    main: {
        temp: number;
        temp_min: number;
        temp_max: number;
        humidity: number;
    };
    weather: {
        main: string;
        description: string;
        icon: string;
    }[];
    dt_txt: string;
}

export interface ForecastData {
    list: ForecastItem[];
}
