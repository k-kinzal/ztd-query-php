<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Dml\HandlerFacts;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * HANDLER ... READ FIRST|NEXT: reads rows of an open handler in natural row order.
 *
 * The facts follow MYSQL-HANDLER-READ-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html.
 *
 * @visibility public
 * @example Reading a scan of a handler
 *     $read = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('HANDLER h READ NEXT WHERE a > 1 LIMIT 5');
 *     [$read->statement->direction, $read->toString()] // => [\SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection::Next, 'HANDLER h READ NEXT WHERE a > 1 LIMIT 5']
 */
final class HandlerScan implements Statement
{
    use Snapshot;

    /**
     * @param OpenHandler $handler The handler read from
     * @param ScanDirection $direction Which row is read
     * @param Scalar|null $where The condition rows must meet
     * @param Limit|null $limit The most rows read
     */
    public function __construct(public readonly OpenHandler $handler, public readonly ScanDirection $direction, public readonly ?Scalar $where = null, public readonly ?Limit $limit = null)
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
        $out->keyword('HANDLER')->node($this->handler)->keyword('READ', $this->direction->value);
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        $out->node($this->limit);
    }
}
