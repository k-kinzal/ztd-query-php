<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml\Handler;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Dml\WriteScope;
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
 * name (MYSQL-HANDLER-TABLE-001); WHERE and key operands see its columns.
 * LIMIT sees no relation. A key must be constant after its dependencies resolve.
 * DEFAULT takes the type and value of its index column, which depend on the open
 * handler. The statement returns the rows read: every column of the
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
     * @param list<Scalar|\SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $values The key values of an index seek
     */
    public function read(OpenHandler $handler, array $values, Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|null $where, ?Limit $limit, Derivation $derivation): void
    {
        $this->functions([...$values, ...($where === null ? [] : [$where])], $derivation);
        $base = $derivation->environment();
        $fact = $derivation->relation($handler, $base);
        $visible = new Environment($derivation->context, $base, [new VisibleRelation($handler, $fact->shape, $handler->name, implicit: (new \SqlSemantics\Platform\MySql\Rules\Query\TableShapes())->implicit($fact))]);
        foreach ($values as $position => $value) {
            $column = new \SqlSemantics\Statement\Shape\Field($position, new \SqlSemantics\Statement\Shape\OutputSlot(null, new \SqlSemantics\Statement\Type\Dependent([new \SqlSemantics\Statement\Reference\Missing\SessionState('handler index key')]), \SqlSemantics\Statement\Type\Nullability::Dependent));
            if ($value instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand || $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand) {
                $derivation->scalar($value, $visible);
            } else {
                (new WriteScope())->value($value, $column, $derivation, $visible);
            }
        }
        if ($where !== null) {
            $derivation->scalar($where, $visible);
        }
        (new TailFacts())->limit($limit, $derivation, $base);
        $fields = [];
        foreach ($fact->shape->slots as $position => $slot) {
            $fields[] = new \SqlSemantics\Statement\Shape\Field($position, $slot, resolution: new \SqlSemantics\Statement\Reference\Column\ResolvedColumn($handler, $slot));
        }
        if (!$fact->shape->complete()) {
            $fields[] = new OpenStar($fact->shape->missing);
        }
        $derivation->output(new QueryFact($fields, $derivation->context->columnNames));
    }

    /**
     * Reports stored-function interpretation in key values or a handler predicate.
     * The restriction precedes handler lookup, even when the routine is absent.
     * Native calls remain expressions of the handler's ordinary resolution scope.
     *
     * @param list<\SqlSemantics\Statement\Node> $operands The key and predicate operands
     */
    public function functions(array $operands, Derivation $derivation): void
    {
        $pending = $operands;
        while ($pending !== []) {
            $node = array_pop($pending);
            if (($node instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall || $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall) && !$node->native($derivation->context->profile->grammar)) {
                $derivation->report(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('stored functions in HANDLER ... READ'));

                return;
            }
            foreach (get_object_vars($node) as $value) {
                foreach (is_array($value) ? $value : [$value] as $child) {
                    if ($child instanceof \SqlSemantics\Statement\Node) {
                        $pending[] = $child;
                    }
                }
            }
        }
    }

}
