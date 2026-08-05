// src/modules/notifications/components/NotificationContainer.tsx

/**
 * Container global dos toasts
 *
 * @author Mauro Sakugawa
 * @created 2026-05-25
 * @license MIT
 * @version 1.0.0
 */

import {
  AnimatePresence,
} from "framer-motion";

import { useNotificationStore }
  from "../store/useNotificationStore";

import NotificationToast
  from "./NotificationToast";

export default function NotificationContainer() {
  const notifications =
    useNotificationStore(
      (state) =>
        state.notifications
    );

  return (
    <div
      className="
        fixed
        top-6
        right-6
        z-[9999]
        flex
        flex-col
        gap-4
      "
    >
      <AnimatePresence>
        {notifications.map(
          (notification) => (
            <NotificationToast
              key={notification.id}
              notification={notification}
            />
          )
        )}
      </AnimatePresence>
    </div>
  );
}