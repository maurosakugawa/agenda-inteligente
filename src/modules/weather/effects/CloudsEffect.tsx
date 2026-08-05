// src/components/effects/CloudsEffect.tsx
import { useState } from "react";

interface Cloud {
  width: number;
  height: number;
  top: number;
  left: number;
  duration: number;
  delay: number;
}

function generateClouds(): Cloud[] {

  return Array.from({
    length: 12,
  }).map(() => ({

    width:
      120 + Math.random() * 200,

    height:
      60 + Math.random() * 120,

    top:
      Math.random() * 80,

    left:
      Math.random() * 100,

    duration:
      25 + Math.random() * 30,

    delay:
      Math.random() * 10,
  }));
}

export default function CloudsEffect() {

  const [clouds] = useState<Cloud[]>(
    generateClouds
  );

  return (

    <div
      className="
        absolute inset-0
        overflow-hidden
        pointer-events-none
      "
    >

      {clouds.map((cloud, i) => (

        <div
          key={i}
          className="
            absolute
            rounded-full
            bg-white/10
            blur-3xl
            animate-cloud-drift
          "
          style={{

            width:
              `${cloud.width}px`,

            height:
              `${cloud.height}px`,

            top:
              `${cloud.top}%`,

            left:
              `${cloud.left}%`,

            animationDuration:
              `${cloud.duration}s`,

            animationDelay:
              `${cloud.delay}s`,
          }}
        />

      ))}

    </div>
  );
}