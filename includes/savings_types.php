<?php

/**
 * Shared account-type classification for the Savings and Investments modules.
 * Both modules live in the same `savings_accounts` table; the type decides
 * which page an account shows up on, and how net worth / FIRE bucket it.
 */

const INVESTMENT_TYPES = ['investment', 'stocks', 'crypto', 'retirement'];

// ponytail: anything not explicitly an investment counts as savings, so an
// unknown/legacy type can never vanish from both pages.
function isInvestmentType($type)
{
    return in_array($type, INVESTMENT_TYPES, true);
}
