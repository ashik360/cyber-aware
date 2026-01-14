const GAME_IDS = ["phishing", "password", "malware", "social"];

function isGameCompleted(gameId) {
  return (
    localStorage.getItem(`game_${gameId}`) === "completed" ||
    localStorage.getItem(gameId) === "true"
  );
}

function markGameCompleted(gameId) {
  localStorage.setItem(`game_${gameId}`, "completed");
  localStorage.setItem(gameId, "true");
}

function awardPoints(gameId, points) {
  const scoreKey = `score_${gameId}`;
  if (localStorage.getItem(scoreKey) === "true") {
    return false;
  }

  const current = Number(localStorage.getItem("totalScore")) || 0;
  localStorage.setItem("totalScore", current + points);
  localStorage.setItem(scoreKey, "true");
  return true;
}

function getProgress() {
  const games = {};
  GAME_IDS.forEach((id) => {
    games[id] = isGameCompleted(id);
  });

  const completedCount = GAME_IDS.filter((id) => games[id]).length;

  return {
    totalScore: Number(localStorage.getItem("totalScore")) || 0,
    games,
    completedCount,
    totalGames: GAME_IDS.length
  };
}
