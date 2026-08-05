import {
  useState,
} from "react";

import {
  MapPin,
  Search,
} from "lucide-react";

interface Props {
  onSearch: (city: string) => void;
  loading: boolean;
  initialCity?: string;
}

export default function CitySearch({
  onSearch,
  loading,
  initialCity = "",
}: Props) {
  const [city, setCity] =
    useState(initialCity);

  const handleSubmit = (
    event: React.FormEvent
  ) => {
    event.preventDefault();

    const normalizedCity = city.trim();

    if (normalizedCity) {
      onSearch(normalizedCity);
    }
  };

  return (
    <form
      onSubmit={handleSubmit}
      className="flex w-full max-w-md gap-2"
    >
      <div className="relative flex-1">
        <MapPin className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-base-content/40" />

        <input
          type="text"
          value={city}
          onChange={(event) =>
            setCity(event.target.value)
          }
          placeholder="Digite uma cidade..."
          className="input input-bordered w-full pl-10"
          aria-label="Cidade para previsão do tempo"
        />
      </div>

      <button
        type="submit"
        className="btn btn-primary"
        disabled={loading || !city.trim()}
        aria-label="Buscar clima"
      >
        {loading ? (
          <span className="loading loading-spinner" />
        ) : (
          <Search className="h-5 w-5" />
        )}
      </button>
    </form>
  );
}
