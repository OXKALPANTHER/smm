import AsyncStorage from "@react-native-async-storage/async-storage";
import { createContext, type PropsWithChildren, useCallback, useContext, useEffect, useMemo, useState } from "react";

import { RoyalApiClient } from "@/lib/api";
import type { AppRole, MobileBootstrap } from "@/lib/types";

type AppState = {
  role: AppRole;
  setRole: (role: AppRole) => void;
  panelUrl: string;
  setPanelUrl: (url: string) => void;
  bootstrap: MobileBootstrap | null;
  loading: boolean;
  error: string | null;
  refresh: (urlOverride?: string) => Promise<void>;
};

const AppContext = createContext<AppState | null>(null);
const PANEL_URL_KEY = "royal-smm-panel-url";

export function RoyalAppProvider({ children }: PropsWithChildren) {
  const [role, setRole] = useState<AppRole>("customer");
  const [panelUrl, setPanelUrlState] = useState("");
  const [bootstrap, setBootstrap] = useState<MobileBootstrap | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => { AsyncStorage.getItem(PANEL_URL_KEY).then((url) => setPanelUrlState(url ?? "")); }, []);

  const setPanelUrl = useCallback((url: string) => {
    const normalized = url.trim().replace(/\/$/, "");
    setPanelUrlState(normalized);
    void AsyncStorage.setItem(PANEL_URL_KEY, normalized);
    setBootstrap(null);
    setError(null);
  }, []);

  const refresh = useCallback(async (urlOverride?: string) => {
    const activePanelUrl = (urlOverride ?? panelUrl).trim().replace(/\/$/, "");
    if (!activePanelUrl) { setError("Add the live panel URL before connecting."); return; }
    setLoading(true); setError(null);
    try {
      const next = await new RoyalApiClient(activePanelUrl).getBootstrap();
      setBootstrap(next); setRole(next.user.role);
    } catch {
      setBootstrap(null);
      setError("The live mobile API is not available yet. The app shell is ready to connect when it is enabled.");
    } finally { setLoading(false); }
  }, [panelUrl]);

  const value = useMemo(() => ({ role, setRole, panelUrl, setPanelUrl, bootstrap, loading, error, refresh }), [role, panelUrl, setPanelUrl, bootstrap, loading, error, refresh]);
  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}

export function useRoyalApp() {
  const state = useContext(AppContext);
  if (!state) throw new Error("useRoyalApp must be used inside RoyalAppProvider");
  return state;
}
