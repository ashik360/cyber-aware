const submitButton = document.getElementById("submitPhishing");
const resultBox = document.getElementById("result");
const timerEl = document.getElementById("timer");
const clues = Array.from(document.querySelectorAll("[data-correct]"));

let remaining = 90;
let timerId = null;

function updateTimer() {
  if (!timerEl) return;
  timerEl.textContent = remaining + "s";
}

function setDisabled(state) {
  clues.forEach((item) => {
    item.disabled = state;
  });
  if (submitButton) submitButton.disabled = state;
}

function startTimer() {
  updateTimer();
  timerId = setInterval(() => {
    remaining -= 1;
    updateTimer();
    if (remaining <= 0) {
      clearInterval(timerId);
      setDisabled(true);
      if (resultBox) {
        resultBox.textContent =
          "Time is up. Review the indicators and try again.";
        resultBox.style.color = "#b45309";
      }
    }
  }, 1000);
}

function scoreSelection() {
  const correctItems = clues.filter((item) => item.dataset.correct === "true");
  const wrongItems = clues.filter((item) => item.dataset.correct !== "true");

  const correctSelected = correctItems.filter((item) => item.checked).length;
  const wrongSelected = wrongItems.filter((item) => item.checked).length;

  const baseScore = Math.round((correctSelected / correctItems.length) * 10);
  const penalty = wrongSelected * 2;
  const finalScore = Math.max(0, Math.min(10, baseScore - penalty));

  return {
    finalScore,
    correctSelected,
    totalCorrect: correctItems.length
  };
}

function handleSubmit() {
  if (!resultBox) return;

  const { finalScore, correctSelected, totalCorrect } = scoreSelection();
  const passed = correctSelected >= Math.ceil(totalCorrect * 0.7);

  if (passed) {
    markGameCompleted("phishing");
    const awarded = awardPoints("phishing", finalScore);
    resultBox.textContent = awarded
      ? "Well done. +" + finalScore + " points added."
      : "Already completed. Score saved earlier.";
    resultBox.style.color = "#15803d";
    setDisabled(true);
    if (timerId) clearInterval(timerId);
  } else {
    resultBox.textContent =
      "Not quite. You found " +
      correctSelected +
      " of " +
      totalCorrect +
      " indicators. Try again.";
    resultBox.style.color = "#b91c1c";
  }
}

if (submitButton) {
  submitButton.addEventListener("click", handleSubmit);
}

if (isGameCompleted("phishing")) {
  setDisabled(true);
  if (resultBox) {
    resultBox.textContent = "Challenge completed. Review the email for practice.";
    resultBox.style.color = "#0f766e";
  }
} else {
  startTimer();
}
