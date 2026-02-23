let scrollTopBeforeOpening = 0;
const shouldScroll = window.innerWidth <= 768;

function resetIncomeForm() {
    document.querySelector("#income-id").value = "";
    document.querySelector("#income-form-title").textContent = "Add Income";
    document.querySelector("#income-form-element").reset();
    document.querySelector("#income-date").value = new Date().toISOString().split('T')[0];
    document.querySelector("#delete-income").style.display = "none";
    document.querySelector("#delete-income").removeAttribute("onClick");
    document.querySelector("#income-recurring-fields").style.display = "";
    document.querySelector("#income-next-payment-group").style.display = "";
    document.querySelector("#income-type").value = "recurring";
}

function addIncome() {
    resetIncomeForm();
    const modal = document.getElementById("income-form");
    modal.classList.add("is-open");
    document.body.classList.add("no-scroll");
}

function closeIncomeForm() {
    const modal = document.getElementById("income-form");
    modal.classList.remove("is-open");
    document.body.classList.remove("no-scroll");
    if (shouldScroll) {
        window.scrollTo(0, scrollTopBeforeOpening);
    }
    resetIncomeForm();
}

function editIncome(id) {
    scrollTopBeforeOpening = window.scrollY;
    document.body.classList.add("no-scroll");

    fetch(`endpoints/income/get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success === false) {
                showErrorMessage(data.message);
                return;
            }

            document.querySelector("#income-form-title").textContent = "Edit Income";
            document.querySelector("#income-id").value = data.id;
            document.querySelector("#income-name").value = data.name;
            document.querySelector("#income-amount").value = data.amount;
            document.querySelector("#income-currency").value = data.currency_id;
            document.querySelector("#income-type").value = data.type;
            document.querySelector("#income-date").value = data.date;
            document.querySelector("#income-notes").value = data.notes || "";
            document.querySelector("#income-inactive").checked = data.inactive == 1;

            if (data.type === "recurring") {
                document.querySelector("#income-frequency").value = data.frequency;
                document.querySelector("#income-cycle").value = data.cycle;
                document.querySelector("#income-next-payment").value = data.next_payment;
                document.querySelector("#income-recurring-fields").style.display = "";
                document.querySelector("#income-next-payment-group").style.display = "";
            } else {
                document.querySelector("#income-recurring-fields").style.display = "none";
                document.querySelector("#income-next-payment-group").style.display = "none";
            }

            if (data.category_id) {
                document.querySelector("#income-category").value = data.category_id;
            }

            const deleteButton = document.querySelector("#delete-income");
            deleteButton.style.display = "block";
            deleteButton.setAttribute("onClick", `deleteIncome(event, ${data.id})`);

            document.getElementById("income-form").classList.add("is-open");
        })
        .catch(error => {
            console.error(error);
            showErrorMessage("Failed to load income entry");
        });
}

function toggleIncomeRecurring() {
    const type = document.querySelector("#income-type").value;
    const recurringFields = document.querySelector("#income-recurring-fields");
    const nextPaymentGroup = document.querySelector("#income-next-payment-group");

    if (type === "one-off") {
        recurringFields.style.display = "none";
        nextPaymentGroup.style.display = "none";
    } else {
        recurringFields.style.display = "";
        nextPaymentGroup.style.display = "";
    }
}

function deleteIncome(event, id) {
    event.preventDefault();
    if (!confirm("Are you sure you want to delete this income entry?")) return;

    fetch("endpoints/income/delete.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": window.csrfToken
        },
        body: JSON.stringify({ id: id })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showErrorMessage(data.message);
            }
        })
        .catch(error => {
            console.error(error);
            showErrorMessage("Failed to delete income");
        });
}

// Form submission
document.getElementById("income-form-element").addEventListener("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append("csrf_token", window.csrfToken);

    fetch(this.action, {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showErrorMessage(data.message);
            }
        })
        .catch(error => {
            console.error(error);
            showErrorMessage("Failed to save income");
        });
});
