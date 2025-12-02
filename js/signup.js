const roleSelect = document.getElementById("roleSelect");
const studentFields = document.getElementById("studentFields");

function toggleFields() {
    if (roleSelect.value === "Student") {
        studentFields.style.display = "block";
    } else if (roleSelect.value === "Teacher") {
        studentFields.style.display = "none";
    }
}

// Run when role changes
roleSelect.addEventListener("change", toggleFields);

// Run on page load
toggleFields();
