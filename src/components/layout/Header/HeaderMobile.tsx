import { useEffect, useState } from "react";
import {
  House,
  Wrench,
  Settings,
  Package,
  ShoppingBag,
  Phone,
  GraduationCap,
} from "lucide-react";

const links = [
  {
    href: "/",
    label: "Accueil",
    icon: House,
    requiresCatalog: false,
  },
  {
    href: "/maintenance",
    label: "Maintenance",
    icon: Wrench,
    requiresCatalog: false,
  },
  {
    href: "/installation",
    label: "Installation",
    icon: Settings,
    requiresCatalog: false,
  },
  {
    href: "/negoce",
    label: "Négoce",
    icon: Package,
    requiresCatalog: false,
  },
  {
    href: "/formation",
    label: "Formation",
    icon: GraduationCap,
    requiresCatalog: false,
  },
  {
    href: "/materiel-industriel",
    label: "Matériel à vendre",
    icon: ShoppingBag,
    requiresCatalog: true,
  },
];

interface Props {
  phone: string;
}

export default function HeaderMobile({ phone }: Props) {
  const [isOpen, setIsOpen] = useState(false);
  const [catalogAvailable, setCatalogAvailable] = useState(false);

  useEffect(() => {
    let active = true;

    fetch("/api/annonces.php", {
      cache: "no-store",
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Erreur API");
        }

        return response.json();
      })
      .then((annonces) => {
        if (!active) return;

        setCatalogAvailable(
          Array.isArray(annonces) && annonces.length > 0,
        );
      })
      .catch(() => {
        if (!active) return;
        setCatalogAvailable(false);
      });

    return () => {
      active = false;
    };
  }, []);

  const visibleLinks = links.filter(
    (link) => !link.requiresCatalog || catalogAvailable,
  );

  return (
    <div className="mobile-menu">
      <button
        className={isOpen ? "menu-toggle is-open" : "menu-toggle"}
        type="button"
        aria-label={isOpen ? "Fermer le menu" : "Ouvrir le menu"}
        aria-expanded={isOpen}
        onClick={() => setIsOpen(!isOpen)}
      >
        <span></span>
        <span></span>
        <span></span>
      </button>

      <nav className={isOpen ? "mobile-panel is-open" : "mobile-panel"}>
        {visibleLinks.map((link) => {
          const Icon = link.icon;

          return (
            <a
              key={link.href}
              href={link.href}
              onClick={() => setIsOpen(false)}
            >
              <Icon size={20} />
              <span>{link.label}</span>
            </a>
          );
        })}

        <a
          className="mobile-panel__phone"
          href={`tel:${phone.replaceAll(" ", "")}`}
          aria-label={`Appeler Hervé au ${phone}`}
          onClick={() => setIsOpen(false)}
        >
          <Phone size={20} />
          <span>{phone}</span>
        </a>

        <a
          className="mobile-panel__button"
          href="/contact"
          onClick={() => setIsOpen(false)}
        >
          Demander un devis
        </a>
      </nav>
    </div>
  );
}
