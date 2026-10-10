<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\Tail\TailFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\OpenStar;

/**
 * Derives the facts of HANDLER ... READ.
 *
 * Rule: MYSQL-HANDLER-READ-001. The handler is an open relation under its
 * name (MYSQL-HANDLER-TABLE-001); WHERE sees it, the key values and LIMIT
 * see no relation. DEFAULT takes the type and value of its index column, which depend on the open handler. The statement returns the rows read: every column of the
 * handler's table, which is session state, so the output is an open star
 * that depends on that state. Terminates: one pass over the finite parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class HandlerFacts
{
    /**
     * Derives a read of a handler and records its rows as the output.
     *
     * @param list<Scalar> $values The key values of an index seek
     */
    public function read(OpenHandler $handler, array $values, ?Scalar $where, ?Limit $limit, Derivation $derivation): void
    {
        $base = $derivation->environment();
        $fact = $derivation->relation($handler, $base);
        foreach ($values as $position => $value) {
            $column = new \SqlSemantics\Statement\Shape\Field($position, new \SqlSemantics\Statement\Shape\OutputSlot(null, new \SqlSemantics\Statement\Type\Dependent($fact->shape->missing), \SqlSemantics\Statement\Type\Nullability::Dependent));
            (new WriteScope())->value($value, $column, $derivation, $base);
        }
        if ($where !== null) {
            $derivation->scalar($where, new Environment($derivation->context, $base, [new VisibleRelation($handler, $fact->shape, $handler->name)]));
        }
        (new TailFacts())->limit($limit, $derivation, $base);
        $derivation->output(new QueryFact([new OpenStar($fact->shape->missing)], $derivation->context->columnNames));
    }
}
