export type Summary = {
    netWorth: number;
    directlyControlledAssets: number;
    heldElsewhere: number;
    investableNetWorth: number;
    liquidAssets: number;
    availableNow?: number;
    availableWithinThreeDays?: number;
    totalAssets?: number;
    emergencyReserveMonths?: number;
    reservedForGoals: number;
    income: number;
    expenses: number;
    freeCashFlow: number;
    emergencyFund: number;
    emergencyCoverageMonths: number;
    liabilities?: number;
    investedThisMonth?: number;
    recurringCommitments?: number;
    savingsRate?: number;
    investmentRate?: number;
};

export type Asset = {
    id: number;
    name: string;
    type: string;
    classification?:
        | 'cash'
        | 'reserved_cash'
        | 'gold'
        | 'investment'
        | 'certificate'
        | 'receivable'
        | 'other';
    quantity: number | null;
    currency: string;
    costBasis: number;
    currentValue: number;
    unitPrice: number | null;
    liquidity: string;
    isLiquid: boolean;
    accountName: string | null;
    notes?: string | null;
    acquiredOn?: string | null;
    gainLoss: number;
    archived?: boolean;
    bucketAllocations?: {
        bucketId: number;
        bucketName: string;
        purpose?: string | null;
        goalName?: string | null;
        targetAmount?: number;
        currentAmount?: number;
        amount: number;
    }[];
};

export type Goal = {
    id: number;
    name: string;
    targetAmount: number;
    allocatedAmount: number;
    remainingAmount: number;
    deadline: string | null;
    monthsRemaining: number | null;
    requiredMonthlyContribution: number;
    plannedMonthlyContribution?: number | null;
    fundingPercent: number;
    onTrack: boolean;
    gapPerMonth: number;
    fundingSources?: {
        assetId: number;
        assetName: string;
        assetType: string;
        currency: string;
        bucketNames: string[];
        amount: number;
    }[];
    status?: string;
    priority?: number;
    notes?: string | null;
    archived?: boolean;
};

export type Bucket = {
    id: number;
    name: string;
    purpose?: string | null;
    color: string;
    goalId?: number | null;
    goalName?: string | null;
    targetAmount: number;
    currentAmount: number;
    archived?: boolean;
    assetCount?: number;
};

export const formatEGP = (amount: number) =>
    new Intl.NumberFormat('en-EG', {
        style: 'currency',
        currency: 'EGP',
        maximumFractionDigits: 0,
    }).format(amount);

export const formatCompactEGP = (amount: number) => {
    if (Math.abs(amount) >= 1_000_000) {
        return `${(amount / 1_000_000).toFixed(1)}m EGP`;
    }

    if (Math.abs(amount) >= 1_000) {
        return `${Math.round(amount / 1_000)}k EGP`;
    }

    return `${Math.round(amount)} EGP`;
};

export const labelize = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
