const progress = getProgress();

document.getElementById("totalScore").textContent = progress.totalScore;

let completed = 0;
if (progress.phishing) completed++;
if (progress.password) completed++;

document.getElementById("completedTasks").textContent = completed + " / 2";
