import { createContext, useContext, useEffect, useState } from 'react';
import { api } from './api.js';

// Shown until /api/farm answers (and if it fails), so the header never flashes empty.
const FALLBACK = {
  name: 'Nông trại Xanh Đà Lạt',
  slogan: 'Rau sạch từ vườn đến bàn ăn',
  address: '',
  phone: '',
  email: '',
  certifications: [],
  plots: [],
  stats: {},
  latest_logs: [],
};

const FarmContext = createContext(FALLBACK);

/** The single farm this shop belongs to: name, story, address, plots (from config/farm.php). */
export function FarmProvider({ children }) {
  const [farm, setFarm] = useState(FALLBACK);

  useEffect(() => {
    api('/farm')
      .then((data) => {
        setFarm({ ...FALLBACK, ...data, loaded: true });
        document.title = `${data.name} – ${data.slogan}`;
      })
      .catch(() => {});
  }, []);

  return <FarmContext.Provider value={farm}>{children}</FarmContext.Provider>;
}

export function useFarm() {
  return useContext(FarmContext);
}
