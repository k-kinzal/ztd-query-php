<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SET TRANSACTION: the isolation level and access mode of later transactions.
 *
 * Rule: MYSQL-SET-TRANSACTION-001. Without a scope keyword the
 * characteristics apply to the next transaction of the session only, and
 * the statement is refused inside a transaction; with SESSION (or LOCAL)
 * they apply to every later transaction of the session; with GLOBAL to
 * later sessions; PERSIST and PERSIST_ONLY (8.0 and later) also persist
 * the setting. One isolation level and one access mode can be written, in
 * either order; the order is kept as written. The statement returns no rows
 * and derives no facts. Terminates: a fixed list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-transaction.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the characteristics
 *     $set = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SET GLOBAL TRANSACTION READ ONLY, ISOLATION LEVEL SERIALIZABLE');
 *     [$set->statement->scope, $set->statement->characteristics, $set->toString()] // => [\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Global, [\SqlSemantics\Platform\MySql\Statement\Utility\Set\AccessMode::ReadOnly, \SqlSemantics\Platform\MySql\Statement\Utility\Set\IsolationLevel::Serializable], 'SET GLOBAL TRANSACTION READ ONLY, ISOLATION LEVEL SERIALIZABLE']
 */
final class SetTransaction implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<IsolationLevel|AccessMode> The characteristics in the order written
     */
    public readonly array $characteristics;

    /**
     * @param array<array-key, object> $characteristics The characteristics in the order written, as a list of one or two IsolationLevel and AccessMode cases of different kinds
     * @param VariableScope|null $scope The scope keyword, or null for the next transaction only
     *
     * @throws InvalidConstruction When a characteristic is not an isolation level or an access mode
     */
    public function __construct(array $characteristics, public readonly ?VariableScope $scope = null)
    {
        Check::input(array_is_list($characteristics) && $characteristics !== [] && count($characteristics) <= 2, 'SET TRANSACTION writes one or two characteristics.');
        $kinds = [];
        $written = [];
        foreach ($characteristics as $characteristic) {
            if (!$characteristic instanceof IsolationLevel && !$characteristic instanceof AccessMode) {
                throw new InvalidConstruction('A transaction characteristic is an isolation level or an access mode.');
            }
            $kinds[$characteristic::class] = true;
            $written[] = $characteristic;
        }
        Check::input(count($kinds) === count($written), 'SET TRANSACTION writes at most one characteristic of each kind.');
        $this->characteristics = $written;
    }

    /**
     * Derives nothing: the statement returns no rows and holds no expression.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes SET, the scope, TRANSACTION and the characteristics separated by commas.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET');
        if ($this->scope !== null) {
            $out->keyword($this->scope->value);
        }
        $out->keyword('TRANSACTION');
        foreach ($this->characteristics as $index => $characteristic) {
            if ($index > 0) {
                $out->symbol(',');
            }
            if ($characteristic instanceof IsolationLevel) {
                $out->keyword('ISOLATION', 'LEVEL');
            }
            $out->keyword(...explode(' ', $characteristic->value));
        }
    }
}
