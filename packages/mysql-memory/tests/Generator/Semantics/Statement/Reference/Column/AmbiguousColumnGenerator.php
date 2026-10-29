<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AmbiguousColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Column\AmbiguousColumn>
 */
final class AmbiguousColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Column\AmbiguousColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Reference\Column\ResolvedColumn>> $candidates Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $qualifier Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $candidates, ?ErisGenerator $qualifier = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Column\AmbiguousColumn {
            return new \SqlSemantics\Statement\Reference\Column\AmbiguousColumn(name: $parts[0], candidates: $parts[1], qualifier: $parts[2]);
        }, Generators::tuple(
            $name,
            $candidates,
            $qualifier ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Statement\Reference\Column\AmbiguousColumn>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Reference\\Column\\AmbiguousColumn' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new AmbiguousColumnGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'b' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            candidates: Generators::tuple(
                new ResolvedColumnGenerator(
                    relation: new \Tests\Generator\Semantics\Statement\RelationGenerator([new \Tests\Generator\MySql\Statement\Relation\JsonTableGenerator(
                        document: new \Tests\Generator\Semantics\Statement\ScalarGenerator([\Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(
                            segments: Generators::tuple(
                                Generators::constant('[]')
                            ),
                            introducer: Generators::constant(null),
                            national: Generators::constant(false),
                            collation: Generators::constant(null)
                        )]),
                        path: \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(
                            segments: Generators::tuple(
                                Generators::constant('$[*]')
                            ),
                            introducer: Generators::constant(null),
                            national: Generators::constant(false),
                            collation: Generators::constant(null)
                        ),
                        columns: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\Call\JsonTableColumnGenerator([new \Tests\Generator\MySql\Statement\Call\Json\OrdinalityColumnGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::map(static fn (int $suffix): string => 'b' . $suffix, Generators::choose(0, 8)),
                                    quote: Generators::constant(null)
                                )
                            )]),
                            new \Tests\Generator\MySql\Statement\Call\JsonTableColumnGenerator([new \Tests\Generator\MySql\Statement\Call\Json\OrdinalityColumnGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::map(static fn (int $suffix): string => 'b' . $suffix, Generators::choose(0, 8)),
                                    quote: Generators::constant(null)
                                )
                            )])
                        ),
                        alias: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $suffix): string => 'j' . $suffix, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::Bare])
                    )]),
                    slot: new \Tests\Generator\Semantics\Statement\Shape\OutputSlotGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $suffix): string => 'b' . $suffix, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        type: new \Tests\Generator\Semantics\Statement\Type\TypeFactGenerator([new \Tests\Generator\Semantics\Statement\Type\KnownGenerator(
                            descriptor: new \Tests\Generator\Semantics\Statement\Type\TypeDescriptorGenerator([new \Tests\Generator\MySql\Statement\Type\Resolved\DomainGenerator(
                                kind: \Tests\Generator\MySql\Statement\Type\Resolved\KindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer]),
                                field: \Tests\Generator\MySql\Statement\Type\Resolved\FieldGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong]),
                                length: Generators::constant(20),
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
                                display: Generators::constant(10),
                                capacity: Generators::constant(null),
                                displayDecimals: Generators::constant(null),
                                storageField: Generators::constant(null)
                            )])
                        )]),
                        nullability: \Tests\Generator\Semantics\Statement\Type\NullabilityGenerator::selecting([\SqlSemantics\Statement\Type\Nullability::Nullable]),
                        column: Generators::constant(null),
                        origin: Generators::constant(null),
                        unnamed: Generators::constant([])
                    ),
                    depth: Generators::constant(0),
                    resultReference: Generators::constant(false)
                ),
                new ResolvedColumnGenerator(
                    relation: new \Tests\Generator\Semantics\Statement\RelationGenerator([new \Tests\Generator\MySql\Statement\Relation\JsonTableGenerator(
                        document: new \Tests\Generator\Semantics\Statement\ScalarGenerator([\Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(
                            segments: Generators::tuple(
                                Generators::constant('[]')
                            ),
                            introducer: Generators::constant(null),
                            national: Generators::constant(false),
                            collation: Generators::constant(null)
                        )]),
                        path: \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(
                            segments: Generators::tuple(
                                Generators::constant('$[*]')
                            ),
                            introducer: Generators::constant(null),
                            national: Generators::constant(false),
                            collation: Generators::constant(null)
                        ),
                        columns: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\Call\JsonTableColumnGenerator([new \Tests\Generator\MySql\Statement\Call\Json\OrdinalityColumnGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::map(static fn (int $suffix): string => 'b' . $suffix, Generators::choose(0, 8)),
                                    quote: Generators::constant(null)
                                )
                            )]),
                            new \Tests\Generator\MySql\Statement\Call\JsonTableColumnGenerator([new \Tests\Generator\MySql\Statement\Call\Json\OrdinalityColumnGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::map(static fn (int $suffix): string => 'b' . $suffix, Generators::choose(0, 8)),
                                    quote: Generators::constant(null)
                                )
                            )])
                        ),
                        alias: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $suffix): string => 'j' . $suffix, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::Bare])
                    )]),
                    slot: new \Tests\Generator\Semantics\Statement\Shape\OutputSlotGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $suffix): string => 'b' . $suffix, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        type: new \Tests\Generator\Semantics\Statement\Type\TypeFactGenerator([new \Tests\Generator\Semantics\Statement\Type\KnownGenerator(
                            descriptor: new \Tests\Generator\Semantics\Statement\Type\TypeDescriptorGenerator([new \Tests\Generator\MySql\Statement\Type\Resolved\DomainGenerator(
                                kind: \Tests\Generator\MySql\Statement\Type\Resolved\KindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer]),
                                field: \Tests\Generator\MySql\Statement\Type\Resolved\FieldGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong]),
                                length: Generators::constant(20),
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
                                display: Generators::constant(10),
                                capacity: Generators::constant(null),
                                displayDecimals: Generators::constant(null),
                                storageField: Generators::constant(null)
                            )])
                        )]),
                        nullability: \Tests\Generator\Semantics\Statement\Type\NullabilityGenerator::selecting([\SqlSemantics\Statement\Type\Nullability::Nullable]),
                        column: Generators::constant(null),
                        origin: Generators::constant(null),
                        unnamed: Generators::constant([])
                    ),
                    depth: Generators::constant(0),
                    resultReference: Generators::constant(false)
                )
            ),
            qualifier: Generators::constant(null)
        )];
    }

}
