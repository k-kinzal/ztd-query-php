<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Constraint;

use Override;

/**
 * An ordered nonempty key with an explicit checking policy.
 *
 * @visibility public
  * @example Inspecting UniqueKey
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('create table t(id integer, code integer, foreign key(id) references p(id), constraint uq unique(code), constraint pk primary key(id))')->tables[0];
 *     $table->constraints[1] instanceof \SqlSemantics\Schema\Constraint\UniqueKey // => true
 */
final class UniqueKey extends \SqlSemantics\Schema\TableConstraint
{
    /**
     * @var non-empty-list<\SqlSemantics\Schema\IndexElement> Validated ordered operands
     */
    public readonly array $keys;

    /**
     * @param list<\SqlSemantics\Schema\IndexElement> $keys
     * @param \SqlSemantics\Model\Write\Policy\ConstraintResponse $onConflict SQLite ON CONFLICT resolution; Default when none is declared
     * @param KeyIndex $index Declared options of the index enforcing the key
     * Constructs a valid declaration.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        array $keys,
        public readonly CheckingTime $checking = CheckingTime::Immediate,
        public readonly bool $nullsDistinct = true,
        ?string $name = null,
        \SqlParser\Parser\Node $source = new \SqlParser\Parser\Node('constraint', 0, []),
        public readonly \SqlSemantics\Model\Write\Policy\ConstraintResponse $onConflict = \SqlSemantics\Model\Write\Policy\ConstraintResponse::Default,
        public readonly KeyIndex $index = new KeyIndex(),
    ) {
        parent::__construct($name, $source);
        \SqlSemantics\Model\Validation\Collections::objects($keys, \SqlSemantics\Schema\IndexElement::class);
        if ($keys === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A key requires distinct column names.');
        }
        $this->keys = \SqlSemantics\Model\Validation\Collections::nonEmpty($keys);
        ConflictClause::check($onConflict, $this->keys[0]->value()->type->dialect);
        $index->check($this->keys[0]->value()->type->dialect);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Schema\ConstraintKind
    {
        return \SqlSemantics\Schema\ConstraintKind::Unique;
    }

    /**
     * Returns the local column names constrained by this declaration.
     * @return list<string>
     */
    #[Override]
    public function localColumns(): array
    {
        return array_values(array_filter(array_map(static fn (\SqlSemantics\Schema\IndexElement $key): ?string => $key instanceof \SqlSemantics\Schema\Index\ColumnKey ? ($key->column->columnBinding()?->column->name ?? $key->column->referenceParts()[0]) : null, $this->keys), static fn (?string $name): bool => $name !== null));
    }
}
