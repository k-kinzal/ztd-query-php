<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AmbiguousAlias values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias>
 */
final class AmbiguousAliasGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Shape\Field>> $candidates Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $candidates)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias {
            return new \SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias(name: $parts[0], candidates: $parts[1]);
        }, Generators::tuple(
            $name,
            $candidates
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Name\\AmbiguousAlias' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new AmbiguousAliasGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'x' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            candidates: Generators::tuple(
                new \Tests\Generator\Semantics\Statement\Shape\FieldGenerator(
                    position: Generators::constant(0),
                    slot: new \Tests\Generator\Semantics\Statement\Shape\OutputSlotGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $suffix): string => 'x' . $suffix, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        type: new \Tests\Generator\Semantics\Statement\Type\TypeFactGenerator([new \Tests\Generator\Semantics\Statement\Type\KnownGenerator(
                            descriptor: new \Tests\Generator\Semantics\Statement\Type\TypeDescriptorGenerator([new \Tests\Generator\MySql\Statement\Type\Resolved\DomainGenerator(
                                kind: \Tests\Generator\MySql\Statement\Type\Resolved\KindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer]),
                                field: \Tests\Generator\MySql\Statement\Type\Resolved\FieldGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong]),
                                length: Generators::constant(2),
                                decimals: Generators::constant(0),
                                unsigned: Generators::constant(false),
                                collation: new \Tests\Generator\MySql\Statement\Type\Resolved\CollationGenerator(
                                    name: Generators::constant('binary'),
                                    id: Generators::constant(63),
                                    charset: new \Tests\Generator\MySql\Statement\Type\Resolved\CharsetGenerator(
                                        name: Generators::constant('binary'),
                                        maxLength: Generators::constant(1)
                                    ),
                                    padSpace: Generators::constant(false)
                                ),
                                members: Generators::constant([]),
                                coercibility: \Tests\Generator\MySql\Statement\Type\Resolved\CoercibilityGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Numeric]),
                                display: Generators::constant(null),
                                capacity: Generators::constant(null),
                                displayDecimals: Generators::constant(null),
                                storageField: Generators::constant(null)
                            )])
                        )]),
                        nullability: \Tests\Generator\Semantics\Statement\Type\NullabilityGenerator::selecting([\SqlSemantics\Statement\Type\Nullability::NotNull]),
                        column: Generators::constant(null),
                        origin: Generators::constant(null),
                        unnamed: Generators::constant([])
                    ),
                    expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )]),
                    resolution: Generators::constant(null)
                ),
                new \Tests\Generator\Semantics\Statement\Shape\FieldGenerator(
                    position: Generators::constant(1),
                    slot: new \Tests\Generator\Semantics\Statement\Shape\OutputSlotGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $suffix): string => 'x' . $suffix, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        type: new \Tests\Generator\Semantics\Statement\Type\TypeFactGenerator([new \Tests\Generator\Semantics\Statement\Type\KnownGenerator(
                            descriptor: new \Tests\Generator\Semantics\Statement\Type\TypeDescriptorGenerator([new \Tests\Generator\MySql\Statement\Type\Resolved\DomainGenerator(
                                kind: \Tests\Generator\MySql\Statement\Type\Resolved\KindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer]),
                                field: \Tests\Generator\MySql\Statement\Type\Resolved\FieldGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong]),
                                length: Generators::constant(2),
                                decimals: Generators::constant(0),
                                unsigned: Generators::constant(false),
                                collation: new \Tests\Generator\MySql\Statement\Type\Resolved\CollationGenerator(
                                    name: Generators::constant('binary'),
                                    id: Generators::constant(63),
                                    charset: new \Tests\Generator\MySql\Statement\Type\Resolved\CharsetGenerator(
                                        name: Generators::constant('binary'),
                                        maxLength: Generators::constant(1)
                                    ),
                                    padSpace: Generators::constant(false)
                                ),
                                members: Generators::constant([]),
                                coercibility: \Tests\Generator\MySql\Statement\Type\Resolved\CoercibilityGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Numeric]),
                                display: Generators::constant(null),
                                capacity: Generators::constant(null),
                                displayDecimals: Generators::constant(null),
                                storageField: Generators::constant(null)
                            )])
                        )]),
                        nullability: \Tests\Generator\Semantics\Statement\Type\NullabilityGenerator::selecting([\SqlSemantics\Statement\Type\Nullability::NotNull]),
                        column: Generators::constant(null),
                        origin: Generators::constant(null),
                        unnamed: Generators::constant([])
                    ),
                    expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )]),
                    resolution: Generators::constant(null)
                )
            )
        )];
    }

}
