let score = 0;

document.querySelectorAll(".phishing").forEach(item => {
  item.addEventListener("click", function () {
    this.style.backgroundColor = "#ffc107";
    score = 10;
  });
});

function checkResult() {
  const result = document.getElementById("result");

  if (score > 0) {
    updateScore(10);
    localStorage.setItem("phishing", "true");

    result.innerHTML = "✅ Correct! +10 points saved.";
    result.style.color = "green";
  } else {
    result.innerHTML = "❌ Missed! Try again.";
    result.style.color = "red";
  }
}
