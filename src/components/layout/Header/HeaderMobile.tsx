import { useState } from "react";
import {
  House,
  Wrench,
  Settings,
  Package,
  ShoppingBag,
  Phone
} from "lucide-react";

const links = [
  {
    href: "/",
    label: "Accueil",
    icon: House
  },
  {
    href: "/maintenance",
    label: "Maintenance",
    icon: Wrench
  },
  {
    href: "/installation",
    label: "Installation",
    icon: Settings
  },
  {
    href: "/negoce",
    label: "Négoce",
    icon: Package
  },
  {
    href: "/materiel-industriel",
    label: "Matériel à vendre",
    icon: ShoppingBag
  }
];

interface Props {
  phone: string;
}

export default function HeaderMobile({ phone }: Props) {
  const [isOpen, setIsOpen] = useState(false);

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
        {links.map((link) => {

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
