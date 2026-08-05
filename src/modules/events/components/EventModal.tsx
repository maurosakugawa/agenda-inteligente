// src/modules/events/components/EventModal.tsx

/**
 * Modal responsável pela criação
 * e edição de eventos.
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT License
 * @version 1.0.0
 */

import {
  motion,
  AnimatePresence,
} from "framer-motion";

import Modal from "../../../components/ui/Modal";

import EventForm from "./EventForm";

import { useEventModal } from "../store/useEventModal";

import { scaleIn } from "../../../animations/scale";

export default function EventModal() {
  const isOpen = useEventModal(
    (state) => state.isOpen
  );

  const close = useEventModal(
    (state) => state.close
  );

  const editingEvent = useEventModal(
    (state) => state.editingEvent
  );

  return (
    <AnimatePresence>
      {isOpen && (
        <Modal
          isOpen={isOpen}
          onClose={close}
          title={
            editingEvent
              ? "Editar evento"
              : "Novo evento"
          }
        >
          <motion.div
            variants={scaleIn}
            initial="hidden"
            animate="visible"
            exit="hidden"
          >
            <EventForm
              key={
                editingEvent?.id ?? "new"
              }
            />
          </motion.div>
        </Modal>
      )}
    </AnimatePresence>
  );
}