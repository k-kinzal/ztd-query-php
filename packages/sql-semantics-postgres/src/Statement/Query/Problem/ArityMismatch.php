<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Two column counts that PostgreSQL requires to agree differ.
 *
 * @visibility public
 * @example Reporting arms of different widths
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 UNION SELECT 1, 2');
 *     $query->facts->diagnostics[0]->message() // => 'each UNION query must have the same number of columns'
 * @example Refusing equal counts
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityMismatch(\SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityRule::ValuesRows, 'VALUES', 2, 2) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ArityMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * @param ArityRule $rule The rule that requires the counts to agree
     * @param string $subject The operator or relation name the message names
     * @param int $available The count the first part has
     * @param int $specified The count the other part has
     */
    public function __construct(public readonly ArityRule $rule, public readonly string $subject, public readonly int $available, public readonly int $specified)
    {
        Check::input($available >= 0 && $specified >= 0 && $available !== $specified, 'An arity mismatch has two different counts.');
    }

    /**
     * Describes the problem in the words of PostgreSQL.
     */
    public function message(): string
    {
        return sprintf($this->rule->value, $this->subject, $this->available, $this->specified);
    }
}
