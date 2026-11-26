<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Dml\HandlerFacts;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;

/**
 * HANDLER ... READ index FIRST|NEXT|PREV|LAST: reads rows of an open handler in the order of an index.
 *
 * The facts follow MYSQL-HANDLER-READ-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html.
 *
 * @visibility SqlSemantics
 */
final class HandlerIndexRead implements Statement
{
    use Snapshot;

    /**
     * @param OpenHandler $handler The handler read from
     * @param Name $index The index whose order is followed; `PRIMARY` names the primary key
     * @param IndexDirection $direction Which row is read
     * @param Scalar|null $where The condition rows must meet
     * @param Limit|null $limit The most rows read
     */
    public function __construct(public readonly OpenHandler $handler, public readonly Name $index, public readonly IndexDirection $direction, public readonly ?Scalar $where = null, public readonly ?Limit $limit = null)
    {
    }

    /**
     * Derives the handler, WHERE and LIMIT, and records the rows read.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new HandlerFacts())->read($this->handler, [], $this->where, $this->limit, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('HANDLER')->node($this->handler)->keyword('READ')->name($this->index, NameUse::Label)->keyword($this->direction->value);
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        $out->node($this->limit);
    }
}
