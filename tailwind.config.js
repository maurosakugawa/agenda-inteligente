import daisyui from "daisyui";

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],

  theme: {
    extend: {
      colors: {
        "header-bg": "var(--header-bg)",
        "search-bg": "var(--search-bg)",
        "search-text": "var(--search-text)",
      },
    },
  },

  plugins: [daisyui],

  daisyui: {
    themes: [
      {
        light: {
          primary: "#7c3aed",
          secondary: "#8b5cf6",
          accent: "#a855f7",

          neutral: "#1e293b",

          "base-100": "#ffffff",
          "base-200": "#f1f5f9",
          "base-300": "#e2e8f0",
          "base-content": "#0f172a",

          info: "#38bdf8",
          success: "#22c55e",
          warning: "#facc15",
          error: "#ef4444",

          header: "#8b5cf6",

        },
      },

      {
        dark: {
          primary: "#8b5cf6",
          secondary: "#7c3aed",
          accent: "#a855f7",

          neutral: "#0f172a",

          "base-100": "#111827",
          "base-200": "#1f2937",
          "base-300": "#374151",
          "base-content": "#f9fafb",

          info: "#38bdf8",
          success: "#22c55e",
          warning: "#facc15",
          error: "#ef4444",

          header: "#22c55e",

        },
      },
    ],

    darkTheme: "dark",
  },
};