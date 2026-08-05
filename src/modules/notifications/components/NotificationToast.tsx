// src/modules/notifications/components/NotificationToast.tsx

/**
 * Toast individual
 *
 * @author Mauro Sakugawa
 * @created 2026-05-25
 * @license MIT
 * @version 1.0.0
 */

import {
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Info,
} from "lucide-react";

import { motion } from "framer-motion";

import type {
  Notification,
} from "../types/notification.types";

interface Props {
  notification: Notification;
}

export default function NotificationToast({
  notification,
}: Props) {
  const icons = {
    success: CheckCircle2,
    error: XCircle,
    warning: AlertTriangle,
    info: Info,
  };

  const colors = {
    success:
      "border-success bg-success/10",

    error:
      "border-error bg-error/10",

    warning:
      "border-warning bg-warning/10",

    info:
      "border-info bg-info/10",
  };

  const Icon =
    icons[notification.type];

  return (
    <motion.div
      initial={{
        opacity: 0,
        y: -20,
        scale: 0.95,
      }}
      animate={{
        opacity: 1,
        y: 0,
        scale: 1,
      }}
      exit={{
        opacity: 0,
        x: 100,
      }}
      className={`
        w-96
        rounded-2xl
        border
        backdrop-blur-xl
        shadow-xl
        p-4
        flex
        gap-4
        ${colors[notification.type]}
      `}
    >
      <Icon className="mt-1" />

      <div className="flex-1">
        <h3 className="font-bold">
          {notification.title}
        </h3>

        {notification.message && (
          <p className="text-sm opacity-70 mt-1">
            {notification.message}
          </p>
        )}
      </div>
    </motion.div>
  );
}