import { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { LogIn, UserPlus, Loader2 } from 'lucide-react';
import { useAuthStore } from '../store/useAuthStore';

interface LoginLocationState {
  from?: {
    pathname?: string;
  };
}

export default function LoginPage() {
  const [isRegister, setIsRegister] = useState(false);
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const { login, register, loading, error, clearError } = useAuthStore();
  const navigate = useNavigate();
  const location = useLocation();

  const locationState =
    location.state as LoginLocationState | null;

  const from =
    locationState?.from?.pathname
    ?? '/';

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    clearError();
    try {
      if (isRegister) {
        await register(username, password);
        setIsRegister(false);
      } else {
        await login(username, password);
        navigate(from, { replace: true });
      }
    } catch {
      // erro já está na store
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary/10 to-secondary/10">
      <div className="card w-full max-w-md bg-base-100 shadow-2xl">
        <div className="card-body">
          <h2 className="card-title text-2xl justify-center mb-6">
            {isRegister ? 'Criar Conta' : 'Entrar'}
          </h2>

          {error && (
            <div className="alert alert-error mb-4">
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="form-control">
              <label className="label"><span className="label-text">Usuário</span></label>
              <input
                type="text"
                className="input input-bordered"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                required
              />
            </div>

            <div className="form-control">
              <label className="label"><span className="label-text">Senha</span></label>
              <input
                type="password"
                className="input input-bordered"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </div>

            <button
              type="submit"
              className="btn btn-primary w-full"
              disabled={loading}
            >
              {loading ? (
                <Loader2 className="w-5 h-5 animate-spin" />
              ) : isRegister ? (
                <><UserPlus className="w-5 h-5" /> Criar conta</>
              ) : (
                <><LogIn className="w-5 h-5" /> Entrar</>
              )}
            </button>
          </form>

          <div className="text-center mt-4">
            <button
              onClick={() => { setIsRegister(!isRegister); clearError(); }}
              className="btn btn-link"
            >
              {isRegister ? 'Já tem conta? Entrar' : 'Não tem conta? Criar'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
