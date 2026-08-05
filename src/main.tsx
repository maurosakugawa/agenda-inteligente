// src/main.tsx
import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter } from "react-router-dom";

import ThemeProvider from "./contexts/ThemeProvider";
import App from "./App";

import "./index.css";
import "./styles/theme.css";
import "./styles/calendar.css";

const rootElement =
  document.getElementById("root");

if (!rootElement) {
  throw new Error(
    'Elemento raiz "#root" não encontrado.'
  );
}

ReactDOM.createRoot(rootElement).render(
  <React.StrictMode>
    <ThemeProvider>
      <BrowserRouter>
        <App />
      </BrowserRouter>
    </ThemeProvider>
  </React.StrictMode>
);
