let scrollTopBeforeOpening = 0;
const shouldScroll = window.innerWidth <= 768;

function resetAccountForm() {
    document.querySelector("#account-id").value = "";
    document.querySelector("#account-form-title").textContent = "Add Account";
    document.querySelector("#account-form-element").reset();
    document.querySelector("#delete-account").style.display = "none";
    document.querySelector("#delete-account").removeAttribute("onClick");
}

function addAccount() {
    resetAccountForm();
    document.getElementById("account-form").classList.add("is-open");
    document.body.classList.add("no-scroll");
}

function closeAccountForm() {
    document.getElementById("account-form").classList.remove("is-open");
    document.body.classList.remove("no-scroll");
    if (shouldScroll) window.scrollTo(0, scrollTopBeforeOpening);
    resetAccountForm();
}

function editAccount(id) {
    scrollTopBeforeOpening = window.scrollY;
    document.body.classList.add("no-scroll");

    fetch("endpoints/savings/getaccounts.php")
        .then(response => response.json())
        .then(accounts => {
            const account = accounts.find(a => a.id == id);
            if (!account) {
                showErrorMessage("Account not found");
                return;
            }

            document.querySelector("#account-form-title").textContent = "Edit Account";
            document.querySelector("#account-id").value = account.id;
            document.querySelector("#account-name").value = account.name;
            document.querySelector("#account-type").value = account.type;
            document.querySelector("#account-currency").value = account.currency_id;
            document.querySelector("#account-institution").value = account.institution || "";
            document.querySelector("#account-notes").value = account.notes || "";
            document.querySelector("#account-inactive").checked = account.inactive == 1;

            const deleteButton = document.querySelector("#delete-account");
            deleteButton.style.display = "block";
            deleteButton.setAttribute("onClick", `deleteAccount(event, ${account.id})`);

            document.getElementById("account-form").classList.add("is-open");
        })
        .catch(error => {
            console.error(error);
            showErrorMessage("Failed to load account");
        });
}

function deleteAccount(event, id) {
    event.preventDefault();
    if (!confirm("Are you sure you want to delete this account and all its balance history?")) return;

    fetch("endpoints/savings/deleteaccount.php", {
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
            showErrorMessage("Failed to delete account");
        });
}

// Snapshot form
function resetSnapshotForm() {
    document.querySelector("#snapshot-id").value = "";
    document.querySelector("#snapshot-form-title").textContent = "Record Balance";
    document.querySelector("#snapshot-form-element").reset();
    document.querySelector("#snapshot-date").value = new Date().toISOString().split('T')[0];
}

function addSnapshot() {
    resetSnapshotForm();
    document.getElementById("snapshot-form").classList.add("is-open");
    document.body.classList.add("no-scroll");
}

function closeSnapshotForm() {
    document.getElementById("snapshot-form").classList.remove("is-open");
    document.body.classList.remove("no-scroll");
    if (shouldScroll) window.scrollTo(0, scrollTopBeforeOpening);
    resetSnapshotForm();
}

// Account form submission
document.getElementById("account-form-element").addEventListener("submit", function (e) {
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
            showErrorMessage("Failed to save account");
        });
});

// Snapshot form submission
document.getElementById("snapshot-form-element").addEventListener("submit", function (e) {
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
            showErrorMessage("Failed to save balance snapshot");
        });
});

// Load savings chart
function loadSavingsChart() {
    fetch("endpoints/savings/getsnapshots.php")
        .then(response => response.json())
        .then(snapshots => {
            if (!snapshots || snapshots.length === 0) return;

            const canvas = document.getElementById("savingsChart");
            if (!canvas) return;

            // Group by account
            const accountData = {};
            const allDates = new Set();

            snapshots.forEach(s => {
                if (!accountData[s.account_name]) {
                    accountData[s.account_name] = {};
                }
                accountData[s.account_name][s.date] = s.balance;
                allDates.add(s.date);
            });

            const sortedDates = Array.from(allDates).sort();
            const colors = [
                'rgba(54, 162, 235, 1)', 'rgba(255, 99, 132, 1)', 'rgba(75, 192, 192, 1)',
                'rgba(255, 206, 86, 1)', 'rgba(153, 102, 255, 1)', 'rgba(255, 159, 64, 1)'
            ];

            const datasets = Object.keys(accountData).map((name, i) => {
                const data = sortedDates.map(date => accountData[name][date] ?? null);
                return {
                    label: name,
                    data: data,
                    borderColor: colors[i % colors.length],
                    backgroundColor: colors[i % colors.length].replace('1)', '0.1)'),
                    fill: true,
                    tension: 0.3,
                    spanGaps: true
                };
            });

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: sortedDates,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat(undefined, { 
                                        style: 'decimal', 
                                        minimumFractionDigits: 0 
                                    }).format(value);
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + 
                                        new Intl.NumberFormat(undefined, { 
                                            style: 'decimal', 
                                            minimumFractionDigits: 2 
                                        }).format(context.parsed.y);
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => console.error("Failed to load savings chart:", error));
}

// Initialize chart on page load
document.addEventListener("DOMContentLoaded", loadSavingsChart);
