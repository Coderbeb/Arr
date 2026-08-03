'use client';
import { useEffect, useRef, useState } from 'react';

export default function CountUp({ target }: { target: number }) {
  const [value, setValue] = useState(0);
  const ref = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    const start = 0;
    const duration = 800;
    const steps = 40;
    const increment = target / steps;
    let current = start;
    let step = 0;

    if (ref.current) clearInterval(ref.current);

    ref.current = setInterval(() => {
      step++;
      current = Math.min(target, current + increment);
      setValue(parseFloat(current.toFixed(2)));
      if (step >= steps) { setValue(target); clearInterval(ref.current!); }
    }, duration / steps);

    return () => { if (ref.current) clearInterval(ref.current); };
  }, [target]);

  return <>{value.toFixed(2)}</>;
}
