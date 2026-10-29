<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete CreateTablespace values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No CreateTablespace forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace) {
                throw new LogicException('Publishing CreateTablespace must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $datafile Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $logfileGroup Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<list<bool>>|null $optionsCommas Generated list boundaries
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace>
     */
    public static function operands(ErisGenerator $name, ?ErisGenerator $datafile = null, ?ErisGenerator $logfileGroup = null, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $optionsCommas = null): ErisGenerator
    {
        return Generators::bind(Generators::tuple($name, $datafile ?? Generators::constant(null), $logfileGroup ?? Generators::constant(null), $options ?? Generators::constant([]), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)), static function (array $parts) use ($optionsCommas): ErisGenerator {
            return Generators::map(static fn (array $commas): \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace => new \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace(name: $parts[0], datafile: $parts[1], logfileGroup: $parts[2], options: $parts[3], statementInformation: $parts[4], inputSource: $parts[5], optionsCommas: $commas), $optionsCommas ?? Generators::vector(max(0, count($parts[3]) - 1), Generators::bool()));
        });
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace>>
     */
    public static function forms(): iterable
    {
        yield 'CreateTablespace/141' => CreateTablespaceGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: Generators::constant([])
        );
        yield 'CreateTablespace/172' => CreateTablespaceGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: Generators::tuple(
                new Option\StorageOptionGenerator([new Option\WaitOptionGenerator(
                    wait: Generators::constant(true)
                )])
            )
        );
        yield 'CreateTablespace/173' => CreateTablespaceGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: Generators::tuple(
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
        yield 'CreateTablespace/174' => CreateTablespaceGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: Generators::tuple(
                new Option\StorageOptionGenerator([new Option\NodegroupOptionGenerator(
                    group: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                        text: Generators::constant('1'),
                        hexadecimal: Generators::constant(false)
                    )
                )])
            )
        );
        yield 'CreateTablespace/175' => CreateTablespaceGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: Generators::tuple(
                new Option\StorageOptionGenerator([new Option\EngineOptionGenerator(
                    engine: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                )])
            )
        );
        yield 'CreateTablespace/176' => CreateTablespaceGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: Generators::tuple(
                new Option\StorageOptionGenerator([new Option\CommentOptionGenerator(
                    comment: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    )
                )])
            )
        );
        yield 'CreateTablespace/177' => CreateTablespaceGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: Generators::tuple(
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
        yield 'CreateTablespace/additional/60' => [\SqlSemantics\Contract\GrammarRelease::MySql847, CreateTablespaceGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('ts'),
                quote: Generators::constant(null)
            ),
            datafile: Generators::constant(null),
            logfileGroup: Generators::constant(null),
            options: Generators::tuple(
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
