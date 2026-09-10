import { useEffect, useState } from "react";
import {
  GraduationCap,
  House,
  Package,
  Phone,
  Settings,
  ShoppingBag,
  Wrench,
} from "lucide-react";

const baseLinks = [
  { href: "/", label: "Accueil", icon: House },
  { href: "/maintenance", label: "Maintenance", icon: Wrench },
  { href: "/installation", label: "Installation", icon: Settings },
  { href: "/negoce", label: "Négoce", icon: Package },
  { href: "/formation", label: "Formation", icon: GraduationCap },
];

interface Props {
  phone: string;
  currentPath: string;
  showMaterialCatalog: boolean;
}

export default function HeaderMobile({
  phone,
  currentPath,
  showMaterialCatalog,
}: Props) {
  const [isOpen, setIsOpen] = useState(false);

  const links = showMaterialCatalog
    ? [
        ...baseLinks,
        {
          href: "/materiel-industriel",
          label: "Matériel à vendre",
          icon: ShoppingBag,
        },
      ]
    : baseLinks;

  useEffect(() => {
    if (!isOpen) return;

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") setIsOpen(false);
    };

    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [isOpen]);

  return (
    <div className="mobile-menu">
      <button
        className={isOpen ? "menu-toggle is-open" : "menu-toggle"}
        type="button"
        aria-label={isOpen ? "Fermer le menu" : "Ouvrir le menu"}
        aria-expanded={isOpen}
        aria-controls="mobile-navigation"
        onClick={() => setIsOpen((open) => !open)}
      >
        <span></span>
        <span></span>
        <span></span>
      </button>

      <nav
        id="mobile-navigation"
        className={isOpen ? "mobile-panel is-open" : "mobile-panel"}
        aria-label="Navigation mobile"
        aria-hidden={!isOpen}
      >
        {links.map((link) => {
          const Icon = link.icon;
          const isCurrent = currentPath === link.href;

          return (
            <a
              key={link.href}
              href={link.href}
              aria-current={isCurrent ? "page" : undefined}
              tabIndex={isOpen ? 0 : -1}
              onClick={() => setIsOpen(false)}
            >
              <Icon size={20} aria-hidden="true" />
              <span>{link.label}</span>
            </a>
          );
        })}

        <a
          className="mobile-panel__phone"
          href={`tel:${phone.replaceAll(" ", "")}`}
          aria-label={`Appeler Hervé au ${phone}`}
          tabIndex={isOpen ? 0 : -1}
          onClick={() => setIsOpen(false)}
        >
          <Phone size={20} aria-hidden="true" />
          <span>{phone}</span>
        </a>

        <a
          className="mobile-panel__button"
          href="/contact"
          tabIndex={isOpen ? 0 : -1}
          onClick={() => setIsOpen(false)}
        >
          Demander un devis
        </a>
      </nav>
    </div>
  );
}
