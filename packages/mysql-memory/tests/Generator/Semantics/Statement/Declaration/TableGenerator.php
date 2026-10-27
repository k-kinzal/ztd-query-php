<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Declaration;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Table values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Declaration\Table>
 */
final class TableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Declaration\Table> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Contract\LanguageProfile> $profile Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Declaration\Column>> $columns Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Declaration\ImplicitColumn>>|null $implicit Generated constructor operand
     * @param ErisGenerator<bool>|null $complete Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Declaration\RelationKind>|null $kind Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Declaration\Key>>|null $keys Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>|null>|null $partitions Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $profile, ErisGenerator $columns, ?ErisGenerator $implicit = null, ?ErisGenerator $complete = null, ?ErisGenerator $kind = null, ?ErisGenerator $keys = null, ?ErisGenerator $partitions = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Declaration\Table {
            return new \SqlSemantics\Statement\Declaration\Table(name: $parts[0], profile: $parts[1], columns: $parts[2], implicit: $parts[3], complete: $parts[4], kind: $parts[5], keys: $parts[6], partitions: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $profile,
            $columns,
            $implicit ?? Generators::constant([]),
            $complete ?? Generators::constant(true),
            $kind ?? Generators::constant(\SqlSemantics\Statement\Declaration\RelationKind::BaseTable),
            $keys ?? Generators::constant([]),
            $partitions ?? Generators::constant(null)
        ));
    }

    /**
     * Generates two visible INT columns, an invisible INT column and a unique key.
     *
     * @return ErisGenerator<\SqlSemantics\Statement\Declaration\Table>
     */
    public static function integers(): ErisGenerator
    {
        $domain = new \Tests\Generator\MySql\Statement\Type\Resolved\DomainGenerator(
            \Tests\Generator\MySql\Statement\Type\Resolved\KindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer]),
            \Tests\Generator\MySql\Statement\Type\Resolved\FieldGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Long]),
            length: Generators::constant(11),
        );
        $columns = [];
        foreach (['first_', 'second_', 'hidden_'] as $prefix) {
            $columns[] = new ColumnGenerator(
                new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::map(static fn (int $suffix): string => $prefix . $suffix, Generators::choose(0, 100))),
                new \Tests\Generator\Semantics\Statement\Type\TypeDescriptorGenerator([$domain]),
                \Tests\Generator\Semantics\Statement\Type\NullabilityGenerator::selecting([\SqlSemantics\Statement\Type\Nullability::Nullable]),
            );
        }
        return \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple($columns[0], $columns[1], $columns[2]), static function (array $columns): self {
            $keys = new KeyGenerator(Generators::constant(false), Generators::constant([$columns[0]]));
            $implicit = new ImplicitColumnGenerator(Generators::constant([$columns[2]->name]), Generators::constant($columns[2]));
            return new self(
                new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::map(static fn (int $suffix): string => 'table_' . $suffix, Generators::choose(0, 100))),
                    new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::constant('pbt')),
                ),
                new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(Generators::constant(\SqlSemantics\Contract\GrammarRelease::MySql847)),
                Generators::constant([$columns[0], $columns[1]]),
                implicit: Generators::vector(1, $implicit),
                kind: RelationKindGenerator::selecting([\SqlSemantics\Statement\Declaration\RelationKind::BaseTable]),
                keys: Generators::vector(1, $keys),
            );
        });
    }
}
