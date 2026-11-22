<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Problem;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Two column counts that PostgreSQL requires to agree differ.
 *
 * @visibility SqlSemantics
 * @example Refusing equal counts
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityMismatch(\SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityRule::ValuesRows, 'VALUES', 2, 2) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
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
