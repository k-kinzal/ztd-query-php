<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Model\Statement\Type\TypeDescriptor;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

/**
 * Resolves the ordered native sides of a foreign-key definition.
 * @visibility SqlSemantics
 */
final class ForeignKeyPairLowering
{
    /**
     * Resolves corresponding child and parent field declarations.
     * @return non-empty-list<ForeignKeyPair>
     */
    public function pairs(ForeignKey $definition, Table $child, Table $parent, GrammarRelease $release): array
    {
        $references = $definition->references->columns;
        Check::input(
            $references !== null && count($references) === count($definition->columns),
            'A native foreign key requires an explicit parent field list of the same width.'
        );
        $pairs = [];
        foreach ($definition->columns as $position => $column) {
            $pairs[] = $this->pair($column, $references[$position], $child, $parent, $release);
        }

        return $pairs;
    }

    /**
     * Binds both field coordinates and compares their native storage domains.
     */
    public function pair(ColumnPart $part, Name $referenced, Table $child, Table $parent, GrammarRelease $release): ForeignKeyPair
    {
        $legacy = (new ForeignKeyRemovalSet())->legacy($release);
        Check::input(
            $part->length === null && (!$legacy || $part->direction === null),
            'A native foreign key requires full fields and release-appropriate ordering.'
        );
        $left = $this->column($child, $part->column);
        $right = $this->column($parent, $referenced);

        return new ForeignKeyPair(
            new Identifier($left->name->value),
            new Identifier($right->name->value),
            $this->domain($left, $right, $release),
            $left->nullability === Nullability::Nullable,
            $part->direction === Direction::Descending,
        );
    }

    /**
     * Settles the checked native comparison between the effective field encodings.
     */
    public function domain(Column $child, Column $parent, GrammarRelease $release): ForeignKeyEnumerationDomain|ForeignKeyIntegerDomain|ForeignKeyScalarDomain|ForeignKeyStringDomain
    {
        $left = $this->field($child, $release);
        $right = $this->field($parent, $release);
        if ($left instanceof ForeignKeyEnumerationStorage || $right instanceof ForeignKeyEnumerationStorage) {
            Check::input(
                $left !== null && $right !== null
                    && !$left instanceof ForeignKeyStringStorage && !$right instanceof ForeignKeyStringStorage,
                'A native ENUM or SET reference requires equal-width unsigned field encodings.'
            );

            return new ForeignKeyEnumerationDomain($left, $right);
        }
        if ($left instanceof ForeignKeyIntegerDomain && $right instanceof ForeignKeyIntegerDomain
            && $left->storage === $right->storage && $left->unsigned === $right->unsigned) {
            return $left;
        }
        if ($left instanceof ForeignKeyStringStorage && $right instanceof ForeignKeyStringStorage) {
            return new ForeignKeyStringDomain($left, $right);
        }
        Check::input(
            $left !== null && $right !== null
            && ($left instanceof ForeignKeyScalarStorage || $right instanceof ForeignKeyScalarStorage),
            'A native foreign-key pair needs compatible checked field domains.'
        );

        return new ForeignKeyScalarDomain($left, $right);
    }

    /**
     * Reads the physical field role without retaining the source declaration.
     */
    public function field(Column $column, GrammarRelease $release): ForeignKeyEnumerationStorage|ForeignKeyIntegerDomain|ForeignKeyScalarStorage|ForeignKeyStringStorage|null
    {
        $integer = $this->integer($column->type);
        if ($integer !== null) {
            return new ForeignKeyIntegerDomain($integer[0], $integer[1]);
        }

        return (new ForeignKeyStringLowering())->storage($column)
            ?? (new ForeignKeyScalarLowering())->storage($column->type, $release)
            ?? (new ForeignKeyEnumerationLowering())->storage($column, $release);
    }

    /**
     * Finds the exact physical declared field by native identifier case.
     */
    public function column(Table $table, Name $name): Column
    {
        $case = new NativeIdentifierCase();
        $selected = $case->fold($name->value);
        $matches = array_values(array_filter(
            $table->columns,
            static fn (Column $field): bool => $case->fold($field->name->value) === $selected
        ));
        Check::input(
            $table->complete && count($matches) === 1 && !$matches[0]->generated && $matches[0]->nullability !== Nullability::Dependent,
            'A native foreign key requires a resolved ordinary field.'
        );

        return $matches[0];
    }

    /**
     * Identifies declared integer storage independently of aliases and display width.
     * @return array{IntegerStorage, bool}|null
     */
    public function integer(TypeDescriptor $domain): ?array
    {
        if ($domain instanceof Integral) {
            $storage = match ($domain->kind) {
                IntegralKind::TinyInt => IntegerStorage::Tiny,
                IntegralKind::SmallInt => IntegerStorage::Small,
                IntegralKind::MediumInt => IntegerStorage::Medium,
                IntegralKind::Int => IntegerStorage::Standard,
                IntegralKind::BigInt => IntegerStorage::Wide,
            };

            return [$storage, $domain->unsigned()];
        }

        return $domain instanceof Elementary ? match ($domain->kind) {
            ElementaryKind::Boolean => [IntegerStorage::Tiny, false],
            ElementaryKind::Serial => [IntegerStorage::Wide, true],
            ElementaryKind::Bit, ElementaryKind::Json, ElementaryKind::Vector => null,
        } : null;
    }
}
