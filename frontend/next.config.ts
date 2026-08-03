import type { NextConfig } from "next";
import os from "os";

// Dynamically collect all local network IPs so phones on the same Wi-Fi can load dev resources
function getLocalIPs(): string[] {
  const ips: string[] = [];
  const interfaces = os.networkInterfaces();
  for (const name of Object.keys(interfaces)) {
    for (const iface of interfaces[name] || []) {
      if (iface.family === 'IPv4' && !iface.internal) {
        ips.push(iface.address);
      }
    }
  }
  return ips;
}

const nextConfig: NextConfig = {
  // Only needed in dev for phones on the same Wi-Fi
  ...(process.env.NODE_ENV !== 'production' && {
    allowedDevOrigins: getLocalIPs(),
  }),
};

export default nextConfig;
