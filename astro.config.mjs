// @ts-check
import { defineConfig } from "astro/config";
import react from "@astrojs/react";
import sitemap from "@astrojs/sitemap";
import { features } from "./src/config/features.mjs";

export default defineConfig({
  site: "https://polymaintenance.fr",
  integrations: [
    react(),
    sitemap({
      filter: (page) => {
        const pathname = new URL(page).pathname;

        if (pathname === "/merci" || pathname === "/merci/") return false;

        if (
          !features.materialCatalog &&
          pathname.startsWith("/materiel-industriel")
        ) {
          return false;
        }

        return true;
      },
    }),
  ],
});
