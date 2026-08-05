import { useState } from "react";

import {
  Calendar,
  CheckSquare,
  Cloud,
  LayoutDashboard,
  Loader2,
  LogOut,
  NotebookPen,
  Settings,
  Users,
} from "lucide-react";

import {
  NavLink,
  useNavigate,
} from "react-router-dom";

import { useAuthStore }
  from "../store/useAuthStore";

const items = [
  {
    label: "Dashboard",
    icon: LayoutDashboard,
    path: "/",
  },
  {
    label: "Eventos",
    icon: CheckSquare,
    path: "/events",
  },
  {
    label: "Calendário",
    icon: Calendar,
    path: "/calendar",
  },
  {
    label: "Contatos",
    icon: Users,
    path: "/contacts",
  },
  {
    label: "Notas",
    icon: NotebookPen,
    path: "/notes",
  },
  {
    label: "Clima",
    icon: Cloud,
    path: "/weather",
  },
  {
    label: "Configurações",
    icon: Settings,
    path: "/settings",
  },
];

export function Sidebar() {
  const navigate = useNavigate();

  const user = useAuthStore(
    (state) => state.user
  );

  const logout = useAuthStore(
    (state) => state.logout
  );

  const [loggingOut, setLoggingOut] =
    useState(false);

  const handleLogout = async () => {
    if (loggingOut) {
      return;
    }

    setLoggingOut(true);

    try {
      await logout();
      navigate("/login", {
        replace: true,
      });
    } finally {
      setLoggingOut(false);
    }
  };

  return (
    <aside className="sticky top-0 flex h-screen w-72 shrink-0 flex-col border-r border-base-300 bg-sidebar-bg p-6 transition-colors duration-300">
      <h1 className="mb-2 text-4xl font-bold text-primary">
        Smart Planner
      </h1>

      <p className="mb-8 text-sm text-base-content/60">
        Organize sua vida
      </p>

      <nav className="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto pr-1">
        {items.map((item) => {
          const Icon = item.icon;

          return (
            <NavLink
              key={item.label}
              to={item.path}
              className={({ isActive }) =>
                `flex items-center gap-4 rounded-2xl px-4 py-4 text-base-content transition-all hover:bg-base-300 hover:text-primary ${
                  isActive
                    ? "bg-primary text-primary-content shadow-lg"
                    : ""
                }`
              }
            >
              <Icon size={22} />
              <span className="text-lg">
                {item.label}
              </span>
            </NavLink>
          );
        })}
      </nav>

      <div className="mt-5 border-t border-base-300 pt-5">
        <div className="mb-3 min-w-0 px-2">
          <span className="block text-xs font-semibold uppercase tracking-wide text-base-content/50">
            Usuário conectado
          </span>
          <strong className="mt-1 block truncate text-sm">
            {user?.username || "Usuário"}
          </strong>
        </div>

        <button
          type="button"
          className="btn btn-outline btn-error w-full justify-start"
          onClick={() => {
            void handleLogout();
          }}
          disabled={loggingOut}
        >
          {loggingOut ? (
            <Loader2
              size={18}
              className="animate-spin"
            />
          ) : (
            <LogOut size={18} />
          )}

          {loggingOut
            ? "Saindo..."
            : "Sair da conta"}
        </button>
      </div>
    </aside>
  );
}
