<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Constraint;

use Override;

/**
 * An ordered nonempty key with an explicit checking policy.
 *
 * @visibility public
 */
final class UniqueKey extends \SqlSemantics\Schema\TableConstraint
{
    /**
     * @var non-empty-list<\SqlSemantics\Schema\IndexElement> Validated ordered operands
     */
    public readonly array $keys;

    /**
     * @param list<\SqlSemantics\Schema\IndexElement> $keys
     * Constructs a valid declaration.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        array $keys,
        public readonly CheckingTime $checking = CheckingTime::Immediate,
        public readonly bool $nullsDistinct = true,
        ?string $name = null,
        \SqlParser\Parser\Node $source = new \SqlParser\Parser\Node('constraint', 0, []),
    ) {
        parent::__construct($name, $source);
        \SqlSemantics\Model\Validation\Collections::objects($keys, \SqlSemantics\Schema\IndexElement::class);
        if ($keys === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A key requires distinct column names.');
        }
        $this->keys = \SqlSemantics\Model\Validation\Collections::nonEmpty($keys);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Schema\ConstraintKind
    {
        return \SqlSemantics\Schema\ConstraintKind::Unique;
    }

    #[Override]
    public function localColumns(): array
    {
        return array_values(array_filter(array_map(static fn (\SqlSemantics\Schema\IndexElement $key): ?string => $key instanceof \SqlSemantics\Schema\Index\ColumnKey ? ($key->column->columnBinding()?->column->name ?? $key->column->referenceParts()[0]) : null, $this->keys), static fn (?string $name): bool => $name !== null));
    }
}
