const progress = getProgress();

const moduleConfig = [
  { id: "phishing", badge: "badge-phishing", action: "action-phishing" },
  { id: "password", badge: "badge-password", action: "action-password" },
  { id: "malware", badge: "badge-malware", action: "action-malware" },
  { id: "social", badge: "badge-social", action: "action-social" }
];

const socialUnlocked = progress.completedCount >= 3;

moduleConfig.forEach((module) => {
  const badge = document.getElementById(module.badge);
  const action = document.getElementById(module.action);
  const done = progress.games[module.id];

  if (!badge || !action) return;

  if (module.id === "social" && !socialUnlocked) {
    badge.textContent = "Locked";
    badge.className = "chip chip-danger";
    action.textContent = "Locked";
    action.className = "btn btn-outline-secondary";
    action.setAttribute("aria-disabled", "true");
    action.setAttribute("tabindex", "-1");
    action.setAttribute("href", "#");
    action.addEventListener("click", (event) => event.preventDefault());
    return;
  }

  if (done) {
    badge.textContent = "Completed";
    badge.className = "chip chip-success";
    action.textContent = "Review Module";
  } else {
    badge.textContent = "In Progress";
    badge.className = "chip chip-warning";
    action.textContent = "Start Module";
  }
});
