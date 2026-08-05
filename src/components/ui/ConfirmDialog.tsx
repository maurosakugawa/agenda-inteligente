// src/components/ui/ConfirmDialog.tsx
/**
 * Modal de confirmação reutilizável
 *
 * @author Mauro Sakugawa
 * @created 2026-05-25
 * @license MIT license
 * @version 1.0.0
 */

import Modal from "./Modal";

interface Props {
  isOpen: boolean;

  title: string;

  message: string;

  onConfirm: () => void;

  onCancel: () => void;
}

export default function ConfirmDialog({
  isOpen,
  title,
  message,
  onConfirm,
  onCancel,
}: Props) {
  return (
    <Modal
      isOpen={isOpen}
      onClose={onCancel}
      title={title}
    >
      <div className="space-y-6">
        <p className="text-base-content/70">
          {message}
        </p>

        <div className="flex justify-end gap-3">
          <button
            onClick={onCancel}
            className="
              btn
              btn-ghost
            "
          >
            Cancelar
          </button>

          <button
            onClick={onConfirm}
            className="
              btn
              btn-error
            "
          >
            Excluir
          </button>
        </div>
      </div>
    </Modal>
  );
}