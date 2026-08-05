/**
 * Página do calendário visual
 *
 * @author Mauro Sakugawa
 * @created 2026-05-25
 * @license MIT
 * @version 1.0.0
 */

import MainLayout
  from "../../../layouts/MainLayout";

import EventCalendar
  from "../components/EventCalendar";

export default function CalendarPage() {
  return (
    <MainLayout
      title="Calendário"
      subtitle="Visualize seus eventos"
    >
      <EventCalendar />
    </MainLayout>
  );
}