import {
  Navigate,
  Route,
  Routes,
} from "react-router-dom";

import ProtectedRoute
  from "../components/ProtectedRoute";

import MainLayout
  from "../layouts/MainLayout";

import CalendarPage
  from "../modules/calendar/pages/CalendarPage";

import ContactsPage
  from "../modules/contacts/pages/ContactsPage";

import EventsPage
  from "../modules/events/pages/EventsPage";

import WeatherPage
  from "../modules/weather/pages/WeatherPage";

import DashboardPage
  from "../pages/DashboardPage";

import LoginPage
  from "../pages/LoginPage";

export default function AppRoutes() {
  return (
    <Routes>
      <Route
        path="/login"
        element={<LoginPage />}
      />

      <Route
        path="/"
        element={
          <ProtectedRoute>
            <DashboardPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/dashboard"
        element={
          <Navigate
            to="/"
            replace
          />
        }
      />

      <Route
        path="/events"
        element={
          <ProtectedRoute>
            <EventsPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/calendar"
        element={
          <ProtectedRoute>
            <CalendarPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/weather"
        element={
          <ProtectedRoute>
            <MainLayout
              title="Clima"
              subtitle="Previsão do tempo"
            >
              <WeatherPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      <Route
        path="/contacts"
        element={
          <ProtectedRoute>
            <MainLayout
              title="Contatos"
              subtitle="Gerencie seus contatos"
            >
              <ContactsPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      <Route
        path="*"
        element={
          <Navigate
            to="/"
            replace
          />
        }
      />
    </Routes>
  );
}
