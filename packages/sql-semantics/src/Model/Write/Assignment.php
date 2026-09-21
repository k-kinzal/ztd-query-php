<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One ordered assignment, retaining tuple destinations and their shared value expression.
 *
 * @example Reading structured effects
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('UPDATE t SET id=1');
 *     $statement->writes[0]->targets[0]->binding->column->name // => 'id'
 *
 * @visibility public
 */
final class Assignment
{
    /**
     * @param list<Expression> $targets Resolved or explicitly unresolved storage references
     * @param Expression $value Scalar, row, DEFAULT, or subquery value
     * @param Node $source Assignment syntax, including subscripted destinations
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly array $targets,
        public readonly Expression $value,
        public readonly Node $source,
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($targets, Expression::class);
        if ($targets === []) {
            throw new InvalidStructure('An assignment requires a destination.');
        }
        foreach ($targets as $target) {
            Destination::column($target);
            if ($target->type->dialect !== $value->type->dialect) {
                throw new InvalidStructure('Assignment destinations and values must use the same dialect.');
            }
        }
    }
}
