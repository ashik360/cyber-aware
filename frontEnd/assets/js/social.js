const socialOptions = Array.from(
  document.querySelectorAll("input[name='socialChoice']")
);
const socialSubmit = document.getElementById("submitSocial");
const socialResult = document.getElementById("result");

function handleSocialSubmit() {
  if (!socialResult) return;
  const selected = socialOptions.find((item) => item.checked);

  if (!selected) {
    socialResult.textContent = "Select an option before submitting.";
    socialResult.style.color = "#b45309";
    return;
  }

  const isCorrect = selected.dataset.correct === "true";

  if (isCorrect) {
    markGameCompleted("social");
    const awarded = awardPoints("social", 10);
    socialResult.textContent = awarded
      ? "Correct decision. +10 points added."
      : "Already completed. Score saved earlier.";
    socialResult.style.color = "#15803d";
  } else {
    socialResult.textContent =
      "Unsafe choice. Never share credentials. Report it to security.";
    socialResult.style.color = "#b91c1c";
  }
}

if (socialSubmit) {
  socialSubmit.addEventListener("click", handleSocialSubmit);
}

if (isGameCompleted("social")) {
  socialOptions.forEach((item) => {
    item.disabled = true;
  });
  if (socialSubmit) socialSubmit.disabled = true;
  if (socialResult) {
    socialResult.textContent = "Challenge completed. Keep reporting suspicious calls.";
    socialResult.style.color = "#0f766e";
  }
}
