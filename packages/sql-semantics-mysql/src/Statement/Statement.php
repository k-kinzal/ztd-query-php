<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement;

/**
 * One MySQL statement, including a statement nested in a stored program.
 *
 * The generic multi-statement Script is not a MySQL statement operand.
 * Release-specific and enclosing-statement constraints still apply.
 *
 * @visibility public
 * @example Identifying a MySQL statement
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1') instanceof \SqlSemantics\Platform\MySql\Statement\Statement // => true
 */
interface Statement extends \SqlSemantics\Statement\Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    /**
     * Returns the language, result and conditions owned by this statement.
     */
    public function statementInformation(): StatementSemantics;

    /**
     * Completes this statement during construction, before it is published.
     */
    public function initializeStatementInformation(StatementSemantics $information): void;
}
