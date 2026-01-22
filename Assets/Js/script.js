// ---------- ALERTS ----------
function showAlert(message, type="success") {
    let alertDiv = document.createElement("div");
    alertDiv.className = type === "success" ? "alert-success" : "alert-error";
    alertDiv.innerText = message;
    document.body.prepend(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
}

// ---------- SIMPLE FORM VALIDATION ----------
document.addEventListener("DOMContentLoaded", () => {
    const forms = document.querySelectorAll("form");
    forms.forEach(form => {
        form.addEventListener("submit", e => {
            const requiredFields = form.querySelectorAll("[required]");
            let valid = true;
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = "red";
                } else {
                    field.style.borderColor = "#ccc";
                }
            });
            if (!valid) {
                e.preventDefault();
                showAlert("Please fill all required fields!", "error");
            }
        });
    });
});

// ---------- DYNAMIC DROPDOWN (OPTIONAL) ----------
function populatePigeons(pigeons) {
    const select = document.querySelector("select[name='pigeon_id']");
    select.innerHTML = "<option value=''>-- Select Pigeon --</option>";
    pigeons.forEach(p => {
        const option = document.createElement("option");
        option.value = p.id;
        option.textContent = p.ring_number;
        select.appendChild(option);
    });
}
