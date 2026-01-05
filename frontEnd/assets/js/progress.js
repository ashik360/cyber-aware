function getProgress() {
  return {
    totalScore: Number(localStorage.getItem("totalScore")) || 0,
    phishing: localStorage.getItem("phishing") === "true",
    password: localStorage.getItem("password") === "true"
  };
}

function updateScore(points) {
  let current = Number(localStorage.getItem("totalScore")) || 0;
  localStorage.setItem("totalScore", current + points);
}
