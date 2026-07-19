export const categoryLabels = {
  manutention: "Manutention",
  "air-comprime": "Air comprimé",
  bois: "Machines à bois",
  ferronnerie: "Ferronnerie",
  soudage: "Soudage",
  emballage: "Emballage",
} as const;

export const conditionLabels = {
  neuf: "Neuf",
  occasion: "Occasion",
  reconditionne: "Reconditionné",
} as const;

export const statusLabels = {
  available: "Disponible",
  reserved: "Réservé",
  sold: "Vendu",
} as const;

export function formatProductPrice(priceType: "fixed" | "on-request", price?: number) {
  if (priceType === "on-request" || !price) {
    return "Tarif sur demande";
  }

  return (
  new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "EUR",
    maximumFractionDigits: 0,
  }).format(price) + " HT"
);
}
