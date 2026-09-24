<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Constraint;

use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\Schema\ConstraintKind;

/**
 * Adds a PostgreSQL primary key or unique constraint that adopts an existing unique index (USING INDEX), taking its
 * columns from that index; the index is renamed to the constraint name when one is given.
 * @visibility public
 * @example Reading the adopted index
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE UNIQUE INDEX ix ON t(id)'));
 *     $action = $binder->bind('ALTER TABLE t ADD CONSTRAINT u UNIQUE USING INDEX ix DEFERRABLE')->actions[0];
 *     [$action->kind, $action->index, $action->name] // => [\SqlSemantics\Schema\ConstraintKind::Unique, 'ix', 'u']
 *     $action->checking // => \SqlSemantics\Schema\Constraint\CheckingTime::DeferrableImmediate
 * @example Rejecting a check that adopts an index
 *     new \SqlSemantics\Model\Definition\Relation\Constraint\AddIndexConstraint(\SqlSemantics\Schema\ConstraintKind::Check, 'ix'); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AddIndexConstraint implements RelationAction
{
    /**
     * @param ConstraintKind $kind PrimaryKey or Unique
     * @param string $index Name of the existing unique index, in the table's schema
     * @param string|null $name Constraint name; the index name when omitted
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly ConstraintKind $kind,
        public readonly string $index,
        public readonly ?string $name = null,
        public readonly CheckingTime $checking = CheckingTime::Immediate,
    ) {
        if (!in_array($kind, [ConstraintKind::PrimaryKey, ConstraintKind::Unique], true)) {
            throw new InvalidStructure('Only a primary key or unique constraint adopts an existing index.');
        }
        if ($index === '' || $name === '') {
            throw new InvalidStructure('An index-backed constraint uses nonempty names.');
        }
    }
}
