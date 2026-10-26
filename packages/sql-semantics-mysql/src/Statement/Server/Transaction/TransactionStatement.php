<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction;

use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\AnalyzedStatement;

/**
 * Transaction control with a selected language and no catalog binding inputs.
 *
 * SAVEPOINT names and XA identifiers address runtime transaction state. Their
 * absence is an execution condition, not an unresolved schema reference.
 *
 * @visibility public
 * @example Transaction analysis returns the concrete command
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('COMMIT');
 *     $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement // => true
 */
interface TransactionStatement extends Statement, AnalyzedStatement
{
}
