<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CreateTablespace values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace>
 */
final class CreateTablespaceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $datafile Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $logfileGroup Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $datafile = null, ?ErisGenerator $logfileGroup = null, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace(name: $parts[0], datafile: $parts[1], logfileGroup: $parts[2], options: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $datafile ?? Generators::constant(null),
            $logfileGroup ?? Generators::constant(null),
            $options ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace>>
     */
    public static function forms(): iterable
    {
        yield 'CreateTablespace/141' => new CreateTablespaceGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: Generators::constant([])
        );
        yield 'CreateTablespace/172' => new CreateTablespaceGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new Option\StorageOptionGenerator([new Option\WaitOptionGenerator(
                    wait: Generators::constant(true)
                )])
            )
        );
        yield 'CreateTablespace/173' => new CreateTablespaceGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new Option\StorageOptionGenerator([new Option\SizeOptionGenerator(
                    kind: Option\SizeOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind::Initial]),
                    size: new \Tests\Generator\MySql\Statement\Literal\ByteSizeGenerator(
                        number: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                            text: Generators::constant('1'),
                            hexadecimal: Generators::constant(false)
                        ),
                        word: Generators::constant(null)
                    )
                )])
            )
        );
        yield 'CreateTablespace/174' => new CreateTablespaceGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new Option\StorageOptionGenerator([new Option\NodegroupOptionGenerator(
                    group: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                        text: Generators::constant('1'),
                        hexadecimal: Generators::constant(false)
                    )
                )])
            )
        );
        yield 'CreateTablespace/175' => new CreateTablespaceGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new Option\StorageOptionGenerator([new Option\EngineOptionGenerator(
                    engine: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                )])
            )
        );
        yield 'CreateTablespace/176' => new CreateTablespaceGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new Option\StorageOptionGenerator([new Option\CommentOptionGenerator(
                    comment: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    )
                )])
            )
        );
        yield 'CreateTablespace/177' => new CreateTablespaceGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new Option\StorageOptionGenerator([new Option\EncryptionOptionGenerator(
                    encryption: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    )
                )])
            )
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'CreateTablespace/additional/60' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new CreateTablespaceGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('ts'),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new Option\StorageOptionGenerator([new Option\EngineAttributeOptionGenerator(
                    attribute: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('{}'),
                        radix: Generators::constant(null)
                    )
                )])
            )
        )];
    }

}
