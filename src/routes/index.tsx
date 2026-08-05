// src/routes/index.tsx
/**
 *
 * @author Mauro Sakugawa
 * Date: 2026-05-21
 * License: MIT License
 * @version 1.0.0
 */
import EventsPage from '../modules/events/pages/EventsPage'
import { Route } from 'react-router-dom'

<Route
  path="/events"
  element={<EventsPage />}
/>