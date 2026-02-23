// Net Worth Dashboard JS

document.addEventListener("DOMContentLoaded", function() {
    loadNetWorthData();

    // Settings form
    const settingsForm = document.getElementById("nw-settings-form");
    if (settingsForm) {
        settingsForm.addEventListener("submit", function(e) {
            e.preventDefault();
            saveSettings();
        });
    }
});

function toggleSettings() {
    const panel = document.getElementById("nw-settings-panel");
    panel.classList.toggle("is-open");
}

function closeSettings() {
    document.getElementById("nw-settings-panel").classList.remove("is-open");
}

function saveSettings() {
    const formData = new FormData(document.getElementById("nw-settings-form"));
    formData.append("csrf_token", window.csrfToken);

    fetch("endpoints/networth/settings.php", {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeSettings();
                loadNetWorthData();
            } else {
                showErrorMessage(data.message || "Failed to save settings");
            }
        })
        .catch(error => {
            console.error(error);
            showErrorMessage("Failed to save settings");
        });
}

let projectionChart = null;
let incomeExpenseChart = null;
let historicalChart = null;

function loadNetWorthData() {
    fetch("endpoints/networth/calculate.php")
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error("Failed to load net worth data:", data.message);
                return;
            }

            updateSummary(data);
            updateAccountBreakdown(data.account_balances);
            renderProjectionChart(data.projections);
            renderIncomeExpenseChart(data);
            renderHistoricalChart(data.savings_history);
            updateSettingsForm(data.settings);
        })
        .catch(error => console.error("Failed to load net worth data:", error));
}

function formatCurrency(value) {
    const code = window.currencyCode || 'USD';
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: code,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(value);
    } catch (e) {
        return new Intl.NumberFormat(undefined, {
            style: 'decimal',
            minimumFractionDigits: 2
        }).format(value);
    }
}

function updateSummary(data) {
    const summary = data.current_summary;
    document.getElementById("nw-monthly-income").textContent = formatCurrency(summary.monthly_income);
    document.getElementById("nw-monthly-outflow").textContent = formatCurrency(summary.monthly_outflow);

    const net = summary.monthly_income - summary.monthly_outflow;
    const netEl = document.getElementById("nw-monthly-net");
    netEl.textContent = formatCurrency(net);
    netEl.style.color = net >= 0 ? "var(--green)" : "var(--red)";

    document.getElementById("nw-current-networth").textContent = formatCurrency(summary.current_net_worth);
}

function updateAccountBreakdown(accounts) {
    const container = document.getElementById("nw-accounts-breakdown");
    if (!container) return;

    if (!accounts || accounts.length === 0) {
        container.innerHTML = '<p class="no-data">No savings accounts found. <a href="savings.php">Add accounts</a> to see your net worth breakdown.</p>';
        return;
    }

    let html = '<table class="nw-accounts-table"><thead><tr><th>Account</th><th>Type</th><th>Balance</th></tr></thead><tbody>';
    accounts.forEach(account => {
        const balance = account.latest_balance || 0;
        html += `<tr>
            <td>${escapeHtml(account.name)}</td>
            <td><span class="account-type-badge">${escapeHtml(account.type)}</span></td>
            <td class="amount">${formatCurrency(balance)}</td>
        </tr>`;
    });
    html += '</tbody></table>';
    container.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderProjectionChart(projections) {
    const canvas = document.getElementById("networthProjectionChart");
    if (!canvas || !projections || projections.length === 0) return;

    const labels = projections.map(p => p.label);
    const savingsData = projections.map(p => p.savings);
    const investmentData = projections.map(p => p.investments);
    const totalData = projections.map(p => p.total);

    if (projectionChart) projectionChart.destroy();

    projectionChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Total Net Worth',
                    data: totalData,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 3
                },
                {
                    label: 'Savings',
                    data: savingsData,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    fill: false,
                    tension: 0.3,
                    borderWidth: 2,
                    borderDash: [5, 5]
                },
                {
                    label: 'Investments',
                    data: investmentData,
                    borderColor: 'rgba(255, 159, 64, 1)',
                    backgroundColor: 'rgba(255, 159, 64, 0.1)',
                    fill: false,
                    tension: 0.3,
                    borderWidth: 2,
                    borderDash: [5, 5]
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + formatCurrency(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return formatCurrency(value); }
                    }
                }
            }
        }
    });
}

function renderIncomeExpenseChart(data) {
    const canvas = document.getElementById("incomeVsExpensesChart");
    if (!canvas) return;

    const summary = data.current_summary;

    if (incomeExpenseChart) incomeExpenseChart.destroy();

    incomeExpenseChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: ['Monthly Income', 'Expenses', 'Subscriptions', 'Net'],
            datasets: [{
                label: 'Amount',
                data: [
                    summary.monthly_income,
                    summary.monthly_expenses,
                    summary.monthly_subscriptions,
                    summary.monthly_income - summary.monthly_outflow
                ],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    (summary.monthly_income - summary.monthly_outflow) >= 0 
                        ? 'rgba(54, 162, 235, 0.7)' 
                        : 'rgba(255, 99, 132, 0.7)'
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 206, 86, 1)',
                    (summary.monthly_income - summary.monthly_outflow) >= 0 
                        ? 'rgba(54, 162, 235, 1)' 
                        : 'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return formatCurrency(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return formatCurrency(value); }
                    }
                }
            }
        }
    });
}

function renderHistoricalChart(history) {
    const canvas = document.getElementById("savingsHistoryChart");
    if (!canvas || !history || history.length === 0) return;

    const labels = history.map(h => h.date);
    const totals = history.map(h => h.total);

    if (historicalChart) historicalChart.destroy();

    historicalChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Historical Net Worth',
                data: totals,
                borderColor: 'rgba(153, 102, 255, 1)',
                backgroundColor: 'rgba(153, 102, 255, 0.1)',
                fill: true,
                tension: 0.3,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return formatCurrency(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: function(value) { return formatCurrency(value); }
                    }
                }
            }
        }
    });
}

function updateSettingsForm(settings) {
    if (!settings) return;
    const form = document.getElementById("nw-settings-form");
    if (!form) return;

    const returnRate = form.querySelector('[name="expected_return_rate"]');
    const inflationRate = form.querySelector('[name="inflation_rate"]');
    const salaryGrowth = form.querySelector('[name="salary_growth_rate"]');
    const projectionYears = form.querySelector('[name="projection_years"]');

    if (returnRate) returnRate.value = settings.expected_return_rate;
    if (inflationRate) inflationRate.value = settings.inflation_rate;
    if (salaryGrowth) salaryGrowth.value = settings.salary_growth_rate;
    if (projectionYears) projectionYears.value = settings.projection_years;
}
