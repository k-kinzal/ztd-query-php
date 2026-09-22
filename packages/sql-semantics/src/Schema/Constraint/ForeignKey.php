<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Constraint;

use Override;

/**
 * A referencing key and its referential actions.
 *
 * @visibility public
 */
final class ForeignKey extends \SqlSemantics\Schema\TableConstraint
{
    /**
     * @var non-empty-list<string> Validated ordered operands
     */
    public readonly array $columns;

    /**
     * @param list<string> $columns
     * @param list<string> $referencedColumns
     * @param list<string> $deleteColumns
     * Constructs a valid declaration.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        array $columns,
        public readonly \SqlSemantics\Model\Relation\QualifiedName $referencedTable,
        public readonly array $referencedColumns = [],
        public readonly \SqlSemantics\Schema\ReferentialAction $onDelete = \SqlSemantics\Schema\ReferentialAction::NoAction,
        public readonly \SqlSemantics\Schema\ReferentialAction $onUpdate = \SqlSemantics\Schema\ReferentialAction::NoAction,
        public readonly MatchMode $match = MatchMode::Simple,
        public readonly CheckingTime $checking = CheckingTime::Immediate,
        public readonly array $deleteColumns = [],
        ?string $name = null,
        \SqlParser\Parser\Node $source = new \SqlParser\Parser\Node('constraint', 0, []),
    ) {
        parent::__construct($name, $source);
        \SqlSemantics\Model\Validation\Collections::strings($columns);
        \SqlSemantics\Model\Validation\Collections::strings($referencedColumns);
        \SqlSemantics\Model\Validation\Collections::strings($deleteColumns);
        if ($columns === [] || ($referencedColumns !== [] && count($columns) !== count($referencedColumns))) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A foreign key requires matching nonempty key widths.');
        }
        if ($deleteColumns !== [] && !in_array($onDelete, [\SqlSemantics\Schema\ReferentialAction::SetNull, \SqlSemantics\Schema\ReferentialAction::SetDefault], true)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('Affected columns belong only to a SET referential action.');
        }
        $this->columns = \SqlSemantics\Model\Validation\Collections::nonEmpty($columns);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Schema\ConstraintKind
    {
        return \SqlSemantics\Schema\ConstraintKind::ForeignKey;
    }

    #[Override]
    public function localColumns(): array
    {
        return $this->columns;
    }
}
