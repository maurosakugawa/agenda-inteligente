// src/components/effects/WeatherParticles.tsx
import RainEffect from "./RainEffect";
import SnowEffect from "./SnowEffect";
import CloudsEffect from "./CloudsEffect";
import LightningEffect from "./LightningEffect";
import AuroraEffect from "./AuroraEffect";

interface Props {
  type: string;
}

export default function WeatherParticles({
  type,
}: Props) {

  switch (type) {

    case "rain":
      return <RainEffect />;

    case "snow":
      return <SnowEffect />;

    case "clouds":
      return <CloudsEffect />;

    case "lightning":
      return <LightningEffect />;

    case "aurora":
      return <AuroraEffect />;

    default:
      return null;
  }
}