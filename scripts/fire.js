// FIRE Calculator JS

var contributionSource = "savings"; // "savings" or "net"

document.addEventListener("DOMContentLoaded", function () {
    // Bind all inputs to recalculate
    const inputs = document.querySelectorAll(".fire-form input");
    inputs.forEach(function (input) {
        input.addEventListener("input", calculate);
    });

    // Monthly toggle buttons
    setupMonthlyToggle("contribution-toggle", "fire-annual-contribution", "contribution-hint");
    setupMonthlyToggle("expenses-toggle", "fire-annual-expenses", "expenses-hint");

    // Initial calculation
    calculate();
});

let fireChart = null;

// ============================================
// Currency formatting
// ============================================

function formatCurrency(value) {
    const code = window.currencyCode || "USD";
    try {
        return new Intl.NumberFormat(undefined, {
            style: "currency",
            currency: code,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    } catch (e) {
        return new Intl.NumberFormat(undefined, {
            style: "decimal",
            minimumFractionDigits: 0,
        }).format(value);
    }
}

// ============================================
// Monthly toggle for contribution/expenses
// ============================================

function setupMonthlyToggle(toggleId, inputId, hintId) {
    const toggle = document.getElementById(toggleId);
    if (!toggle) return;

    toggle.addEventListener("click", function () {
        const input = document.getElementById(inputId);
        const hint = document.getElementById(hintId);
        const currentVal = parseFloat(input.value) || 0;
        const isMonthly = toggle.dataset.mode === "monthly";

        if (isMonthly) {
            // Switch back to annual
            toggle.dataset.mode = "annual";
            input.value = Math.round(currentVal * 12);
            hint.textContent = "";
        } else {
            // Switch to monthly
            toggle.dataset.mode = "monthly";
            input.value = Math.round(currentVal / 12);
            hint.textContent = "Showing monthly value (stored as annual)";
        }
        calculate();
    });
}

function getAnnualValue(inputId, toggleId) {
    const toggle = document.getElementById(toggleId);
    const val = parseFloat(document.getElementById(inputId).value) || 0;
    if (toggle && toggle.dataset.mode === "monthly") {
        return val * 12;
    }
    return val;
}

function setContributionSource(source) {
    contributionSource = source;

    // Update button states
    document.getElementById("contribution-source-savings").classList.toggle("active", source === "savings");
    document.getElementById("contribution-source-net").classList.toggle("active", source === "net");

    // Get the annual value for the selected source
    var annualValue = source === "savings"
        ? (window.fireSavingsContribution || 0)
        : (window.fireNetContribution || 0);

    // Update the input (respecting monthly/annual toggle state)
    var toggle = document.getElementById("contribution-toggle");
    var input = document.getElementById("fire-annual-contribution");
    if (toggle && toggle.dataset.mode === "monthly") {
        input.value = Math.round(annualValue / 12);
    } else {
        input.value = Math.round(annualValue);
    }

    // Update hint text
    var sourceHint = document.getElementById("contribution-source-hint");
    if (sourceHint) {
        sourceHint.textContent = source === "savings"
            ? "From monthly contributions in Savings & Investments"
            : "What remains after net income minus expenses";
    }

    calculate();
}

// ============================================
// Core FIRE calculations
// ============================================

function futureValue(presentValue, annualContribution, rate, years) {
    if (rate === 0) {
        return presentValue + annualContribution * years;
    }
    var compoundFactor = Math.pow(1 + rate, years);
    return presentValue * compoundFactor + annualContribution * ((compoundFactor - 1) / rate);
}

function presentValueCalc(futureVal, rate, years) {
    if (years <= 0) return futureVal;
    return futureVal / Math.pow(1 + rate, years);
}

function yearsToTarget(presentVal, annualContribution, rate, target) {
    if (presentVal >= target) return 0;
    if (rate === 0) {
        if (annualContribution <= 0) return Infinity;
        return (target - presentVal) / annualContribution;
    }

    var numerator = annualContribution + target * rate;
    var denominator = annualContribution + presentVal * rate;

    if (denominator <= 0 || numerator <= denominator) {
        // Iterative fallback
        var years = 0;
        var current = presentVal;
        var maxYears = 100;
        while (current < target && years < maxYears) {
            current = current * (1 + rate) + annualContribution;
            years++;
        }
        return years >= maxYears ? Infinity : years;
    }

    var years = Math.log(numerator / denominator) / Math.log(1 + rate);
    if (years < 0 || years > 100) return Infinity;
    return years;
}

function generateProjections(currentAge, currentSavings, annualContribution, expectedReturn, inflationRate, years) {
    var projections = [];
    var portfolio = currentSavings;
    var totalContributions = currentSavings;
    var currentYear = new Date().getFullYear();

    for (var i = 0; i <= years; i++) {
        var inflationAdjusted = portfolio / Math.pow(1 + inflationRate, i);
        projections.push({
            age: currentAge + i,
            year: currentYear + i,
            portfolio: Math.round(portfolio),
            totalContributions: Math.round(totalContributions),
            inflationAdjusted: Math.round(inflationAdjusted),
        });
        portfolio = portfolio * (1 + expectedReturn) + annualContribution;
        totalContributions += annualContribution;
    }
    return projections;
}

function calculateStandardFIRE(inputs) {
    var fireNumber = inputs.annualExpenses / inputs.withdrawalRate;
    var realReturn = (1 + inputs.expectedReturn) / (1 + inputs.inflationRate) - 1;
    var yrsToFIRE = yearsToTarget(inputs.currentSavings, inputs.annualContribution, realReturn, fireNumber);
    var fireAge = inputs.currentAge + yrsToFIRE;

    var yearsToRetirement = Math.max(0, inputs.retirementAge - inputs.currentAge);
    var coastFireNumber = presentValueCalc(fireNumber, realReturn, yearsToRetirement);

    var savingsRate = inputs.annualIncome > 0 ? inputs.annualContribution / inputs.annualIncome : 0;

    var projectionYears = Math.min(Math.ceil(yrsToFIRE) + 10, 50);
    var projections = generateProjections(
        inputs.currentAge,
        inputs.currentSavings,
        inputs.annualContribution,
        inputs.expectedReturn,
        inputs.inflationRate,
        projectionYears
    );

    return {
        fireNumber: Math.round(fireNumber),
        yearsToFIRE: Math.round(yrsToFIRE * 10) / 10,
        fireAge: Math.round(fireAge * 10) / 10,
        projections: projections,
        savingsRate: savingsRate,
        monthlyContribution: inputs.annualContribution / 12,
        coastFireNumber: Math.round(coastFireNumber),
    };
}

// ============================================
// Main calculate & render
// ============================================

function calculate() {
    var inputs = {
        currentAge: parseInt(document.getElementById("fire-current-age").value) || 30,
        retirementAge: parseInt(document.getElementById("fire-retirement-age").value) || 55,
        currentSavings: parseFloat(document.getElementById("fire-current-savings").value) || 0,
        annualContribution: getAnnualValue("fire-annual-contribution", "contribution-toggle"),
        annualIncome: parseFloat(document.getElementById("fire-annual-income").value) || 0,
        annualExpenses: getAnnualValue("fire-annual-expenses", "expenses-toggle"),
        expectedReturn: (parseFloat(document.getElementById("fire-expected-return").value) || 7) / 100,
        inflationRate: (parseFloat(document.getElementById("fire-inflation-rate").value) || 3) / 100,
        withdrawalRate: (parseFloat(document.getElementById("fire-withdrawal-rate").value) || 4) / 100,
    };

    var results = calculateStandardFIRE(inputs);

    // Update metric cards
    document.getElementById("fire-number").textContent = formatCurrency(results.fireNumber);
    document.getElementById("fire-years").textContent =
        isFinite(results.yearsToFIRE) ? results.yearsToFIRE.toFixed(1) + " yrs" : "N/A";
    document.getElementById("fire-age-subtitle").textContent =
        isFinite(results.fireAge) ? "At age " + Math.round(results.fireAge) : "";
    document.getElementById("fire-savings-rate").textContent =
        (results.savingsRate * 100).toFixed(1) + "%";
    document.getElementById("fire-monthly-subtitle").textContent =
        formatCurrency(Math.round(results.monthlyContribution)) + "/month";
    document.getElementById("fire-coast-number").textContent = formatCurrency(results.coastFireNumber);

    // Update progress bar
    updateProgressBar(inputs.currentSavings, results.fireNumber);

    // Update explanation
    updateExplanation(inputs, results);

    // Update chart
    renderChart(results.projections, results.fireNumber);
}

// ============================================
// Progress bar
// ============================================

function updateProgressBar(currentSavings, fireNumber) {
    var pct = fireNumber > 0 ? Math.min((currentSavings / fireNumber) * 100, 100) : 0;
    var bar = document.getElementById("fire-progress-bar");
    var text = document.getElementById("fire-progress-text");
    var currentLabel = document.getElementById("fire-progress-current");
    var targetLabel = document.getElementById("fire-progress-target");

    bar.style.width = pct.toFixed(1) + "%";
    text.textContent = pct.toFixed(1) + "%";
    currentLabel.textContent = formatCurrency(currentSavings);
    targetLabel.textContent = formatCurrency(fireNumber);

    // Color the bar based on progress
    if (pct >= 100) {
        bar.style.backgroundColor = "var(--green, #22c55e)";
    } else if (pct >= 50) {
        bar.style.backgroundColor = "var(--main-color, #3b82f6)";
    } else {
        bar.style.backgroundColor = "var(--accent-color, #f59e0b)";
    }
}

// ============================================
// Explanation text
// ============================================

function updateExplanation(inputs, results) {
    var expensesFormatted = formatCurrency(inputs.annualExpenses);
    var wrPct = (inputs.withdrawalRate * 100).toFixed(1);
    var fnFormatted = formatCurrency(results.fireNumber);

    document.getElementById("fire-explain-number").innerHTML =
        "Your <strong>FIRE Number</strong> (" + fnFormatted + ") is calculated as your annual expenses (" +
        expensesFormatted + ") divided by your withdrawal rate (" + wrPct + "%).";

    if (isFinite(results.yearsToFIRE)) {
        document.getElementById("fire-explain-years").innerHTML =
            "At your current savings rate, you'll reach financial independence in approximately <strong>" +
            results.yearsToFIRE.toFixed(1) + " years</strong> (at age " + Math.round(results.fireAge) + ").";
    } else {
        document.getElementById("fire-explain-years").innerHTML =
            "With your current savings and contribution rate, reaching your FIRE number may not be feasible. " +
            "Consider increasing contributions or reducing expenses.";
    }
}

// ============================================
// Chart rendering
// ============================================

function renderChart(projections, fireNumber) {
    var ctx = document.getElementById("fireProjectionChart");
    if (!ctx) return;

    var labels = projections.map(function (p) { return "Age " + p.age; });
    var portfolioData = projections.map(function (p) { return p.portfolio; });
    var inflationData = projections.map(function (p) { return p.inflationAdjusted; });
    var fireLine = projections.map(function () { return fireNumber; });

    var mainColor = getComputedStyle(document.documentElement).getPropertyValue("--main-color").trim() || "#3b82f6";

    if (fireChart) {
        fireChart.destroy();
    }

    fireChart = new Chart(ctx, {
        type: "line",
        data: {
            labels: labels,
            datasets: [
                {
                    label: "Portfolio Value",
                    data: portfolioData,
                    borderColor: mainColor,
                    backgroundColor: mainColor + "22",
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    borderWidth: 2,
                },
                {
                    label: "Inflation Adjusted",
                    data: inflationData,
                    borderColor: "#94a3b8",
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.3,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    borderWidth: 2,
                },
                {
                    label: "FIRE Target",
                    data: fireLine,
                    borderColor: "#ef4444",
                    borderDash: [10, 5],
                    fill: false,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    borderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: "index",
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: getComputedStyle(document.documentElement).getPropertyValue("--text-color").trim() || "#333",
                    },
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ": " + formatCurrency(context.parsed.y);
                        },
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        color: getComputedStyle(document.documentElement).getPropertyValue("--text-color").trim() || "#333",
                        maxTicksLimit: 12,
                    },
                    grid: {
                        display: false,
                    },
                },
                y: {
                    ticks: {
                        color: getComputedStyle(document.documentElement).getPropertyValue("--text-color").trim() || "#333",
                        callback: function (value) {
                            return formatCurrency(value);
                        },
                    },
                    grid: {
                        color: "rgba(128,128,128,0.15)",
                    },
                },
            },
        },
    });
}
