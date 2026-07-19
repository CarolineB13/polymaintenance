import { defineCollection } from "astro:content";
import { glob } from "astro/loaders";
import { z } from "astro/zod";

const products = defineCollection({
  loader: glob({
    pattern: "**/*.{md,mdx}",
    base: "./src/content/products",
  }),

  schema: ({ image }) =>
    z.object({
      title: z.string(),
      description: z.string(),

      category: z.enum([
        "manutention",
        "air-comprime",
        "bois",
        "ferronnerie",
        "soudage",
        "emballage",
      ]),

      condition: z.enum(["neuf", "occasion", "reconditionne"]),
      priceType: z.enum(["fixed", "on-request"]).default("on-request"),
      price: z.number().positive().optional(),

      status: z.enum(["available", "reserved", "sold"]).default("available"),
      featured: z.boolean().default(false),
      featuredOrder: z.number().int().nonnegative().default(99),
      demo: z.boolean().default(false),

      brand: z.string().optional(),
      model: z.string().optional(),
      year: z.number().int().min(1950).optional(),
      location: z.string().optional(),

      image: image(),
      imageAlt: z.string(),
      publishedAt: z.coerce.date(),
    }),
});

export const collections = { products };
