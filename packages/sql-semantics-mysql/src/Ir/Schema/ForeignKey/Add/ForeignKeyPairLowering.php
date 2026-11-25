<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringLowering;
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
        $legacy = (new ForeignKeyRemovalSet())->legacy($release);
        $pairs = [];
        foreach ($definition->columns as $position => $column) {
            $pairs[] = $this->pair($column, $references[$position], $child, $parent, $legacy);
        }

        return $pairs;
    }

    /**
     * Binds both field coordinates and compares their native storage domains.
     */
    public function pair(ColumnPart $part, Name $referenced, Table $child, Table $parent, bool $legacy): ForeignKeyPair
    {
        Check::input(
            $part->length === null && (!$legacy || $part->direction === null),
            'A native foreign key requires full fields and release-appropriate ordering.'
        );
        $left = $this->column($child, $part->column);
        $right = $this->column($parent, $referenced);

        return new ForeignKeyPair(
            new Identifier($left->name->value),
            new Identifier($right->name->value),
            $this->domain($left, $right),
            $left->nullability === Nullability::Nullable,
            $part->direction === Direction::Descending,
        );
    }

    /**
     * Settles a compatible native integer or string comparison.
     */
    public function domain(Column $child, Column $parent): ForeignKeyIntegerDomain|ForeignKeyStringDomain
    {
        $left = $this->integer($child->type);
        $right = $this->integer($parent->type);
        if ($left !== null && $left === $right) {
            return new ForeignKeyIntegerDomain($left[0], $left[1]);
        }
        $strings = new ForeignKeyStringLowering();
        $childType = $strings->storage($child);
        $parentType = $strings->storage($parent);
        Check::input($childType !== null && $parentType !== null, 'A native foreign-key pair needs compatible checked field domains.');

        return new ForeignKeyStringDomain($childType, $parentType);
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
