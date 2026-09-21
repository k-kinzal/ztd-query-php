<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Conflict handling is a separate conditional write stage, not the INSERT row filter.
 *
 * @example Reading structured effects
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT INTO t VALUES(1) ON CONFLICT DO NOTHING');
 *     $statement->conflicts[0]->action // => 'nothing'
 *
 * @visibility public
 */
final class ConflictAction
{
    /**
     * @param string $action nothing, update, or replace
     * @param list<Expression> $keys Conflict index expressions
     * @param string|null $constraint Named conflict constraint
     * @param Expression|null $indexPredicate Partial-index inference predicate
     * @param list<Assignment> $assignments Ordered conflict-update assignments
     * @param Expression|null $where Conflict-update row predicate
     * @param Node $source Original conflict clause
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly string $action,
        public readonly array $keys,
        public readonly ?string $constraint,
        public readonly ?Expression $indexPredicate,
        public readonly array $assignments,
        public readonly ?Expression $where,
        public readonly Node $source,
    ) {
        if (!in_array($action, ['nothing', 'update', 'replace'], true)) {
            throw new InvalidStructure('Invalid conflict action.');
        }
        if ($action !== 'update' && ($assignments !== [] || $where !== null)) {
            throw new InvalidStructure('Only a conflict UPDATE can carry assignments or a row predicate.');
        }
    }
}
