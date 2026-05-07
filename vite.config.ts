import { defineConfig } from "vite";
import symfony from "vite-plugin-symfony";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
  plugins: [symfony(), tailwindcss()],
  build: {
    manifest: true,
    rolldownOptions: {
      input: {
        app: "./assets/main.ts",
      },
    },
  },
  server: {
    host: "0.0.0.0",
    port: 5173,
    strictPort: true,
    origin: `${process.env.DDEV_PRIMARY_URL_WITHOUT_PORT}:5173`,
    cors: {
      origin: /https?:\/\/([A-Za-z0-9\-.]+)?(\.ddev\.site)(?::\d+)?$/,
    },
    headers: {
      "Access-Control-Allow-Origin": "*",
    },
  },
});
