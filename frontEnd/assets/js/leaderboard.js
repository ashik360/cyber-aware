const progress = getProgress();
const yourScoreEl = document.getElementById("yourScore");

if (yourScoreEl) {
  yourScoreEl.textContent = progress.totalScore;
}
