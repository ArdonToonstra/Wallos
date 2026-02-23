let scrollTopBeforeOpening = 0;
const shouldScroll = window.innerWidth <= 768;

function resetExpenseForm() {
    document.querySelector("#expense-id").value = "";
    document.querySelector("#expense-form-title").textContent = "Add Expense";
    document.querySelector("#expense-form-element").reset();
    document.querySelector("#expense-date").value = new Date().toISOString().split('T')[0];
    document.querySelector("#delete-expense").style.display = "none";
    document.querySelector("#delete-expense").removeAttribute("onClick");
    document.querySelector("#expense-recurring-fields").style.display = "";
    document.querySelector("#expense-next-payment-group").style.display = "";
    document.querySelector("#expense-type").value = "recurring";
}

function addExpense() {
    resetExpenseForm();
    const modal = document.getElementById("expense-form");
    modal.classList.add("is-open");
    document.body.classList.add("no-scroll");
}

function closeExpenseForm() {
    const modal = document.getElementById("expense-form");
    modal.classList.remove("is-open");
    document.body.classList.remove("no-scroll");
    if (shouldScroll) {
        window.scrollTo(0, scrollTopBeforeOpening);
    }
    resetExpenseForm();
}

function editExpense(id) {
    scrollTopBeforeOpening = window.scrollY;
    document.body.classList.add("no-scroll");

    fetch(`endpoints/expenses/get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success === false) {
                showErrorMessage(data.message);
                return;
            }

            document.querySelector("#expense-form-title").textContent = "Edit Expense";
            document.querySelector("#expense-id").value = data.id;
            document.querySelector("#expense-name").value = data.name;
            document.querySelector("#expense-amount").value = data.amount;
            document.querySelector("#expense-currency").value = data.currency_id;
            document.querySelector("#expense-type").value = data.type;
            document.querySelector("#expense-date").value = data.date;
            document.querySelector("#expense-notes").value = data.notes || "";
            document.querySelector("#expense-inactive").checked = data.inactive == 1;

            if (data.type === "recurring") {
                document.querySelector("#expense-frequency").value = data.frequency;
                document.querySelector("#expense-cycle").value = data.cycle;
                document.querySelector("#expense-next-payment").value = data.next_payment;
                document.querySelector("#expense-recurring-fields").style.display = "";
                document.querySelector("#expense-next-payment-group").style.display = "";
            } else {
                document.querySelector("#expense-recurring-fields").style.display = "none";
                document.querySelector("#expense-next-payment-group").style.display = "none";
            }

            if (data.payment_method_id) {
                document.querySelector("#expense-payment-method").value = data.payment_method_id;
            }
            if (data.payer_user_id) {
                document.querySelector("#expense-payer").value = data.payer_user_id;
            }
            if (data.category_id) {
                document.querySelector("#expense-category").value = data.category_id;
            }

            const deleteButton = document.querySelector("#delete-expense");
            deleteButton.style.display = "block";
            deleteButton.setAttribute("onClick", `deleteExpense(event, ${data.id})`);

            document.getElementById("expense-form").classList.add("is-open");
        })
        .catch(error => {
            console.error(error);
            showErrorMessage("Failed to load expense");
        });
}

function toggleExpenseRecurring() {
    const type = document.querySelector("#expense-type").value;
    const recurringFields = document.querySelector("#expense-recurring-fields");
    const nextPaymentGroup = document.querySelector("#expense-next-payment-group");

    if (type === "one-off") {
        recurringFields.style.display = "none";
        nextPaymentGroup.style.display = "none";
    } else {
        recurringFields.style.display = "";
        nextPaymentGroup.style.display = "";
    }
}

function deleteExpense(event, id) {
    event.preventDefault();
    if (!confirm("Are you sure you want to delete this expense?")) return;

    fetch("endpoints/expenses/delete.php", {
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
            showErrorMessage("Failed to delete expense");
        });
}

// Form submission
document.getElementById("expense-form-element").addEventListener("submit", function (e) {
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
            showErrorMessage("Failed to save expense");
        });
});
