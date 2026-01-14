const progress = getProgress();

const totalScoreEl = document.getElementById("totalScore");
const completedTasksEl = document.getElementById("completedTasks");
const riskLevelEl = document.getElementById("riskLevel");
const currentLevelEl = document.getElementById("currentLevel");
const progressBarEl = document.getElementById("levelProgress");
const progressLabelEl = document.getElementById("levelProgressLabel");
const nextActionLabelEl = document.getElementById("nextActionLabel");
const nextActionLinkEl = document.getElementById("nextActionLink");

if (totalScoreEl) {
  totalScoreEl.textContent = progress.totalScore;
}

if (completedTasksEl) {
  completedTasksEl.textContent =
    progress.completedCount + " / " + progress.totalGames;
}

const percent = Math.round(
  (progress.completedCount / progress.totalGames) * 100
);

if (progressBarEl) {
  progressBarEl.style.width = percent + "%";
}

if (progressLabelEl) {
  progressLabelEl.textContent = percent + "%";
}

if (currentLevelEl) {
  let level = "Beginner";
  if (progress.completedCount >= 4 && progress.totalScore >= 35) {
    level = "Intermediate";
  }
  if (progress.totalScore >= 70) {
    level = "Advanced";
  }
  currentLevelEl.textContent = level;
}

if (riskLevelEl) {
  let risk = "High";
  if (progress.totalScore >= 30) risk = "Medium";
  if (progress.totalScore >= 60) risk = "Low";
  riskLevelEl.textContent = risk;
}

const gameStatusEls = document.querySelectorAll("[data-game-status]");
gameStatusEls.forEach((el) => {
  const gameId = el.getAttribute("data-game-status");
  const done = progress.games[gameId];
  el.textContent = done ? "Completed" : "Pending";
  el.classList.remove("success", "warn", "locked");
  el.classList.add(done ? "success" : "warn");
});

const socialUnlocked = progress.completedCount >= 3;
const actionButtons = document.querySelectorAll("[data-game-action]");
actionButtons.forEach((btn) => {
  const gameId = btn.getAttribute("data-game-action");
  if (gameId !== "social") return;
  if (socialUnlocked) {
    btn.textContent = "Start";
    btn.removeAttribute("aria-disabled");
  } else {
    btn.textContent = "Locked";
    btn.setAttribute("aria-disabled", "true");
    btn.setAttribute("href", "#");
    btn.addEventListener("click", (event) => event.preventDefault());
    const socialStatus = document.querySelector("[data-game-status='social']");
    if (socialStatus) {
      socialStatus.textContent = "Locked";
      socialStatus.classList.remove("success", "warn");
      socialStatus.classList.add("locked");
    }
  }
});

if (nextActionLabelEl && nextActionLinkEl) {
  const nextGame = [
    { id: "phishing", label: "Phishing Trap", link: "game-phishing.html" },
    { id: "password", label: "Password Forge", link: "game-password.html" },
    { id: "malware", label: "Malware Radar", link: "game-malware.html" },
    { id: "social", label: "Social Shield", link: "game-social-engineering.html" }
  ].find((game) => !progress.games[game.id]);

  if (nextGame) {
    nextActionLabelEl.textContent = nextGame.label;
    nextActionLinkEl.href = nextGame.link;
  } else {
    nextActionLabelEl.textContent = "All missions cleared";
    nextActionLinkEl.href = "leaderboard.html";
    nextActionLinkEl.textContent = "View Leaderboard";
  }
}
