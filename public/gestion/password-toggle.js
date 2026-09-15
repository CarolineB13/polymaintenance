document.addEventListener("DOMContentLoaded", () => {
  const buttons = document.querySelectorAll("[data-password-toggle]");

  buttons.forEach((button) => {
    const inputId = button.getAttribute("aria-controls");
    const input = inputId ? document.getElementById(inputId) : null;

    if (!(input instanceof HTMLInputElement)) return;

    button.addEventListener("click", () => {
      const isHidden = input.type === "password";

      input.type = isHidden ? "text" : "password";
      button.classList.toggle("is-visible", isHidden);

      const label = isHidden
        ? "Masquer le mot de passe"
        : "Afficher le mot de passe";

      button.setAttribute("aria-label", label);
      button.setAttribute("title", label);

      input.focus({ preventScroll: true });
    });
  });
});
