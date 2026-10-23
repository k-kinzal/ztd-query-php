<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Dml\Handler\HandlerFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * HANDLER ... READ index op (value, ...): reads rows of an open handler from the first index key that compares as asked.
 *
 * The values are compared with the leading columns of the index. The facts
 * follow MYSQL-HANDLER-READ-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html.
 *
 * @visibility public
 * @example Reading an index seek of a handler
 *     $read = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('HANDLER h READ idx >= (1, 2) LIMIT 3');
 *     [$read->statement->comparison, count($read->statement->values), $read->toString()] // => [\SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison::GreaterOrEqual, 2, 'HANDLER h READ idx >= (1, 2) LIMIT 3']
 */
final class HandlerIndexSeek implements Statement
{
    use Snapshot;

    /**
     * @var list<Scalar|DefaultRequest> The key values in written order
     */
    public readonly array $values;

    /**
     * @param OpenHandler $handler The handler read from
     * @param Name $index The index searched; `PRIMARY` names the primary key
     * @param KeyComparison $comparison How the key is compared with the values
     * @param list<Scalar|DefaultRequest> $values The key values; at least one
     * @param Scalar|null $where The condition rows must meet
     * @param Limit|null $limit The most rows read
     */
    public function __construct(
        public readonly OpenHandler $handler,
        public readonly Name $index,
        public readonly KeyComparison $comparison,
        array $values,
        public readonly ?Scalar $where = null,
        public readonly ?Limit $limit = null,
    ) {
        $accepted = [];
        foreach (Check::listOf($values, Operand::class, 'An index seek compares at least one key value.', 1) as $value) {
            Check::input($value instanceof Scalar || $value instanceof DefaultRequest, 'A HANDLER key value is an expression or DEFAULT.');
            $accepted[] = $value;
        }
        $this->values = $accepted;
        (new \SqlSemantics\Platform\MySql\Rules\Dml\Handler\HandlerOperands())->check($this);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the handler, the key values, WHERE and LIMIT, and records the rows read.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new HandlerFacts())->read($this->handler, $this->values, $this->where, $this->limit, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('HANDLER')->node($this->handler)->keyword('READ')->name($this->index, NameUse::Label)
            ->symbol($this->comparison->value)->symbol('(')->list($this->values)->symbol(')');
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        $out->node($this->limit);
    }
}
