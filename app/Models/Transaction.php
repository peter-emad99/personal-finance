<?php

namespace App\Models;

/**
 * Domain-friendly alias for the canonical ledger transaction model.
 *
 * LedgerTransaction remains the explicit name in services to avoid confusing
 * it with framework/database transaction terminology.
 */
class Transaction extends LedgerTransaction {}
