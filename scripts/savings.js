let scrollTopBeforeOpening = 0;
const shouldScroll = window.innerWidth <= 768;

// Account type lookup populated when the page loads
let accountTypeMap = {};

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
            document.querySelector("#account-monthly-contribution").value = account.monthly_contribution || 0;
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

// ── Snapshot helpers ──────────────────────────────────────────────────────────

function isStocksAccount(accountId) {
    return accountTypeMap[accountId] === 'stocks';
}

function updateSnapshotStockFields() {
    const accountId = document.querySelector("#snapshot-account").value;
    const isStocks = isStocksAccount(accountId);

    document.getElementById("snapshot-shares-group").style.display = isStocks ? "" : "none";
    document.getElementById("snapshot-share-price-group").style.display = isStocks ? "" : "none";
    document.getElementById("snapshot-balance-label").textContent =
        isStocks ? "Total Value (auto-calculated)" : "Current Balance";

    if (!isStocks) {
        document.getElementById("snapshot-shares").value = "";
        document.getElementById("snapshot-share-price").value = "";
    }
}

function onSnapshotAccountChange() {
    updateSnapshotStockFields();
    recalcSnapshotBalance();
}

function recalcSnapshotBalance() {
    const accountId = document.querySelector("#snapshot-account").value;
    if (!isStocksAccount(accountId)) return;

    const shares = parseFloat(document.getElementById("snapshot-shares").value);
    const price = parseFloat(document.getElementById("snapshot-share-price").value);
    if (!isNaN(shares) && !isNaN(price)) {
        document.getElementById("snapshot-balance").value = (shares * price).toFixed(2);
    }
}

// ── Snapshot form ─────────────────────────────────────────────────────────────

function resetSnapshotForm() {
    document.querySelector("#snapshot-id").value = "";
    document.querySelector("#snapshot-form-title").textContent = "Record Balance";
    document.querySelector("#snapshot-form-element").reset();
    document.querySelector("#snapshot-date").value = new Date().toISOString().split('T')[0];
    document.querySelector("#delete-snapshot").style.display = "none";
    document.querySelector("#delete-snapshot").removeAttribute("onClick");
    updateSnapshotStockFields();
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

function editSnapshot(snapshot) {
    scrollTopBeforeOpening = window.scrollY;
    resetSnapshotForm();

    document.querySelector("#snapshot-form-title").textContent = "Edit Balance Record";
    document.querySelector("#snapshot-id").value = snapshot.id;
    document.querySelector("#snapshot-account").value = snapshot.account_id;
    document.querySelector("#snapshot-date").value = snapshot.date;
    document.querySelector("#snapshot-balance").value = snapshot.balance;

    updateSnapshotStockFields();

    if (isStocksAccount(snapshot.account_id)) {
        if (snapshot.shares !== null && snapshot.shares !== undefined) {
            document.querySelector("#snapshot-shares").value = snapshot.shares;
        }
        if (snapshot.share_price !== null && snapshot.share_price !== undefined) {
            document.querySelector("#snapshot-share-price").value = snapshot.share_price;
        }
    }

    const deleteButton = document.querySelector("#delete-snapshot");
    deleteButton.style.display = "block";
    deleteButton.setAttribute("onClick", `deleteSnapshot(event, ${snapshot.id})`);

    document.body.classList.add("no-scroll");
    document.getElementById("snapshot-form").classList.add("is-open");
}

function deleteSnapshot(event, id) {
    event.preventDefault();
    if (!confirm("Are you sure you want to delete this balance record?")) return;

    fetch("endpoints/savings/deletesnapshot.php", {
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
            showErrorMessage("Failed to delete balance record");
        });
}

// ── Form submissions ──────────────────────────────────────────────────────────

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

    const accountId = document.querySelector("#snapshot-account").value;
    if (!isStocksAccount(accountId)) {
        formData.delete("shares");
        formData.delete("share_price");
    }

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

// ── Chart ─────────────────────────────────────────────────────────────────────

function loadSavingsChart(snapshots) {
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
}

// ── Snapshot history list ─────────────────────────────────────────────────────

function renderSnapshotHistory(snapshots) {
    const container = document.getElementById("snapshot-history-list");
    if (!container) return;
    if (!snapshots || snapshots.length === 0) return;

    // Group snapshots by account
    const grouped = {};
    snapshots.forEach(s => {
        if (!grouped[s.account_id]) {
            grouped[s.account_id] = { name: s.account_name, snapshots: [] };
        }
        grouped[s.account_id].snapshots.push(s);
    });

    let html = '';
    Object.values(grouped).forEach(group => {
        const isStocks = group.snapshots.some(s => isStocksAccount(s.account_id));
        // Sort descending by date
        const sorted = group.snapshots.slice().sort((a, b) => b.date.localeCompare(a.date));

        html += `<div class="split-header" style="margin-top: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 600;">${escapeHtml(group.name)}</h3>
        </div>
        <div class="subscriptions" style="margin-top: 0.5rem;">`;

        sorted.forEach(s => {
            const balance = new Intl.NumberFormat(undefined, { style: 'decimal', minimumFractionDigits: 2 }).format(s.balance);
            let detail = s.date;
            if (isStocks && s.shares != null && s.share_price != null) {
                const sharesFormatted = new Intl.NumberFormat(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 6 }).format(s.shares);
                const priceFormatted = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2 }).format(s.share_price);
                detail += ` · ${sharesFormatted} shares @ ${priceFormatted}`;
            }
            html += `<div class="subscription" style="cursor: pointer;" onclick='editSnapshot(${JSON.stringify(s)})'>
                <div class="subscription-main-content">
                    <div class="subscription-info">
                        <div class="subscription-cycle">${escapeHtml(detail)}</div>
                    </div>
                    <div class="subscription-price">
                        <span class="price">${balance}</span>
                    </div>
                </div>
            </div>`;
        });

        html += `</div>`;
    });

    container.innerHTML = html;
}

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener("DOMContentLoaded", function () {
    // Build account type map from the select options
    const accountSelect = document.getElementById("snapshot-account");
    if (accountSelect) {
        Array.from(accountSelect.options).forEach(opt => {
            accountTypeMap[opt.value] = opt.dataset.type;
        });
    }

    // Initialize stock fields visibility
    updateSnapshotStockFields();

    // Load snapshots once, use for both chart and history list
    fetch("endpoints/savings/getsnapshots.php")
        .then(response => response.json())
        // The endpoint returns every account; keep only the ones this page shows.
        .then(snapshots => snapshots.filter(s => s.account_id in accountTypeMap))
        .then(snapshots => {
            loadSavingsChart(snapshots);
            renderSnapshotHistory(snapshots);
        })
        .catch(error => console.error("Failed to load savings data:", error));
});
