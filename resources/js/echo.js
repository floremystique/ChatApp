import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

const scheme =
  import.meta.env.VITE_REVERB_SCHEME ||
  (window.location.protocol === "https:" ? "https" : "http");

const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const port = Number(import.meta.env.VITE_REVERB_PORT) || (scheme === "https" ? 443 : 80);
const key = import.meta.env.VITE_REVERB_APP_KEY || "";

// IMPORTANT: pusher-js throws if neither `cluster` nor `wsHost` is present.
// Adding a dummy cluster + host fallback makes it robust.
const options = {
  broadcaster: "pusher",
  key,

  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || "mt1", // dummy ok for Reverb
  wsHost: host,
  wsPort: port,
  wssPort: port,

  // fallback for some echo/pusher combos
  host: `${host}:${port}`,

  forceTLS: scheme === "https",
  enabledTransports: ["ws", "wss"],
  disableStats: true,
};

try {
  window.Echo = new Echo(options);
  console.log("[Echo] config", { scheme, host, port, keyPresent: !!key });
  const p = window.Echo.connector?.pusher;
  p?.connection?.bind("state_change", (s) => console.log("[Pusher] state", s));
  p?.connection?.bind("connected", () => console.log("[Pusher] connected ✅"));
  p?.connection?.bind("error", (e) => console.log("[Pusher] error ❌", e));
} catch (e) {
  console.log("[Echo] init failed ❌", e, options);
}
