<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One ordered MERGE decision, evaluated only for its specified match state.
 *
 * @example Inspecting a merge action
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE s(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN MATCHED THEN DELETE');
 *     $statement->merge->actions[0]->match // => 'matched'
 *
 * @visibility public
 */
final class MergeAction
{
    /**
     * @param string $match matched, not-matched-by-source, or not-matched-by-target
     * @param string $action update, delete, insert, or nothing
     * @param Expression|null $condition Additional branch condition
     * @param list<Assignment> $assignments Ordered UPDATE assignments
     * @param Insertion|null $insertion INSERT destination mapping
     * @param list<list<Expression>> $rows INSERT values
     * @param Node $source Branch syntax
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly string $match,
        public readonly string $action,
        public readonly ?Expression $condition,
        public readonly array $assignments,
        public readonly ?Insertion $insertion,
        public readonly array $rows,
        public readonly Node $source,
    ) {
        Collections::objects($assignments, Assignment::class);
        foreach ($rows as $row) {
            Collections::objects($row, Expression::class);
        }
        if (!in_array($match, ['matched', 'not-matched-by-source', 'not-matched-by-target'], true) || !in_array($action, ['update', 'delete', 'insert', 'nothing'], true)) {
            throw new InvalidStructure('A MERGE action requires a valid match state and operation.');
        }
        if (($action === 'insert') !== ($insertion !== null) || ($action !== 'insert' && $rows !== []) || ($action !== 'update' && $assignments !== [])) {
            throw new InvalidStructure('MERGE effects must belong to the branch operation.');
        }
    }
}
