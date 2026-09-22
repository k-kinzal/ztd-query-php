<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * MERGE inputs, matching condition, and first-applicable ordered actions.
 *
 * @example Inspecting merge matching
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE s(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN MATCHED THEN DELETE');
 *     $statement->merge->condition->spelling() // => '='
 *
 * @visibility public
 */
final class Merge
{
    /**
     * @param TableUse $target Written relation
     * @param TableUse|Join $input Read-only data source
     * @param Expression $condition Row matching predicate, separate from branch predicates
     * @param list<MergeAction> $actions Branches in SQL evaluation order
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly TableUse $target,
        public readonly TableUse|Join $input,
        public readonly Expression $condition,
        public readonly array $actions,
    ) {
        Collections::objects($actions, MergeAction::class);
        if ($actions === []) {
            throw new InvalidStructure('MERGE requires at least one action.');
        }
        foreach ($actions as $action) {
            if (($action instanceof Decision\MergeRowInsertion || $action instanceof Decision\MergeInsertDefaults) && $action->insertion->target !== $target) {
                throw new InvalidStructure('Every MERGE insertion must use the declared target.');
            }
        }
    }
}
