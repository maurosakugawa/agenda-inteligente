import { useEffect } from "react";
import {
  Navigate,
  useLocation,
} from "react-router-dom";
import { Loader2 } from "lucide-react";

import { useAuthStore }
  from "../store/useAuthStore";

interface Props {
  children: React.ReactNode;
}

export default function ProtectedRoute({
  children,
}: Props) {
  const user = useAuthStore(
    (state) => state.user
  );

  const initialized = useAuthStore(
    (state) => state.initialized
  );

  const checkAuth = useAuthStore(
    (state) => state.checkAuth
  );

  const location = useLocation();

  useEffect(() => {
    if (!initialized) {
      void checkAuth();
    }
  }, [initialized, checkAuth]);

  if (!initialized) {
    return (
      <div className="min-h-screen grid place-items-center bg-base-200">
        <div className="flex items-center gap-3 text-base-content/70">
          <Loader2
            className="animate-spin"
            size={24}
          />
          Verificando sessão...
        </div>
      </div>
    );
  }

  if (!user) {
    return (
      <Navigate
        to="/login"
        state={{ from: location }}
        replace
      />
    );
  }

  return <>{children}</>;
}
