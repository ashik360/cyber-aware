const passwordInput = document.getElementById("passwordInput");
const strengthText = document.getElementById("strengthText");
const strengthBar = document.getElementById("strengthBar");
const resultBox = document.getElementById("result");
const submitButton = document.getElementById("submitPassword");

const rules = [
  { id: "rule-length", test: (value) => value.length >= 10 },
  { id: "rule-upper", test: (value) => /[A-Z]/.test(value) },
  { id: "rule-lower", test: (value) => /[a-z]/.test(value) },
  { id: "rule-number", test: (value) => /[0-9]/.test(value) },
  { id: "rule-symbol", test: (value) => /[^A-Za-z0-9]/.test(value) }
];

function updateRules(value) {
  let score = 0;
  rules.forEach((rule) => {
    const item = document.getElementById(rule.id);
    if (!item) return;
    const passed = rule.test(value);
    item.classList.toggle("text-success", passed);
    item.classList.toggle("text-muted", !passed);
    item.querySelector("span").textContent = passed ? "Met" : "Missing";
    if (passed) score += 1;
  });
  return score;
}

function updateStrength(score) {
  if (!strengthText || !strengthBar) return;
  if (score <= 2) {
    strengthText.textContent = "Weak";
    strengthText.style.color = "#b91c1c";
    strengthBar.style.width = "30%";
    strengthBar.className = "progress-bar bg-danger";
  } else if (score <= 4) {
    strengthText.textContent = "Medium";
    strengthText.style.color = "#b45309";
    strengthBar.style.width = "65%";
    strengthBar.className = "progress-bar bg-warning";
  } else {
    strengthText.textContent = "Strong";
    strengthText.style.color = "#15803d";
    strengthBar.style.width = "100%";
    strengthBar.className = "progress-bar bg-success";
  }
}

function handleInput() {
  const value = passwordInput ? passwordInput.value : "";
  const score = updateRules(value);
  updateStrength(score);
}

function handleSubmit() {
  if (!resultBox || !strengthText) return;
  const strength = strengthText.textContent;

  if (strength === "Strong") {
    markGameCompleted("password");
    const awarded = awardPoints("password", 10);
    resultBox.textContent = awarded
      ? "Great work. +10 points added."
      : "Already completed. Score saved earlier.";
    resultBox.style.color = "#15803d";
    if (submitButton) submitButton.disabled = true;
  } else {
    resultBox.textContent =
      "Keep improving. Add length and variety to reach Strong.";
    resultBox.style.color = "#b91c1c";
  }
}

if (passwordInput) {
  passwordInput.addEventListener("input", handleInput);
}

if (submitButton) {
  submitButton.addEventListener("click", handleSubmit);
}

if (isGameCompleted("password")) {
  if (submitButton) submitButton.disabled = true;
  if (resultBox) {
    resultBox.textContent = "Challenge completed. Try stronger passwords anytime.";
    resultBox.style.color = "#0f766e";
  }
}
