function checkStrength() {
  const password = document.getElementById("password").value;
  const strengthText = document.getElementById("strengthText");
  const strengthBar = document.getElementById("strengthBar");

  let strength = 0;

  if (password.length >= 8) strength++;
  if (/[A-Z]/.test(password)) strength++;
  if (/[0-9]/.test(password)) strength++;
  if (/[^A-Za-z0-9]/.test(password)) strength++;

  if (strength <= 1) {
    strengthText.textContent = "Weak";
    strengthText.style.color = "red";
    strengthBar.style.width = "25%";
    strengthBar.className = "progress-bar bg-danger";
  } else if (strength === 2 || strength === 3) {
    strengthText.textContent = "Medium";
    strengthText.style.color = "orange";
    strengthBar.style.width = "60%";
    strengthBar.className = "progress-bar bg-warning";
  } else {
    strengthText.textContent = "Strong";
    strengthText.style.color = "green";
    strengthBar.style.width = "100%";
    strengthBar.className = "progress-bar bg-success";
  }
}

function submitPassword() {
  const strength = document.getElementById("strengthText").textContent;
  const result = document.getElementById("result");

  if (strength === "Strong") {
  updateScore(10);
  localStorage.setItem("password", "true");

  result.innerHTML = "✅ Strong password! +10 points saved.";
  result.style.color = "green";
}
}
