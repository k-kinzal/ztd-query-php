<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Load;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LoadTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable>
 */
final class LoadTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat> $format Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadLock|null> $lock Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput> $input Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling|null> $duplicates Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CharsetName|null>|null $charset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $compression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $rowTag Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption>>|null $fields Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOption>>|null $lines Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $ignored Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Name\ColumnUse|\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable>>|null $columns Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Assignment>>|null $assignments Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions|null>|null $bulk Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $format, ErisGenerator $lock, ErisGenerator $input, ErisGenerator $duplicates, ErisGenerator $table, ?ErisGenerator $charset = null, ?ErisGenerator $compression = null, ?ErisGenerator $rowTag = null, ?ErisGenerator $fields = null, ?ErisGenerator $lines = null, ?ErisGenerator $ignored = null, ?ErisGenerator $columns = null, ?ErisGenerator $assignments = null, ?ErisGenerator $bulk = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable(format: $parts[0], lock: $parts[1], input: $parts[2], duplicates: $parts[3], table: $parts[4], charset: $parts[5], compression: $parts[6], rowTag: $parts[7], fields: $parts[8], lines: $parts[9], ignored: $parts[10], columns: $parts[11], assignments: $parts[12], bulk: $parts[13], statementInformation: $parts[14], inputSource: $parts[15]);
        }, \Tests\Generator\Operands::tuple(
            $format,
            $lock,
            $input,
            $duplicates,
            $table,
            $charset ?? Generators::constant(null),
            $compression ?? Generators::constant(null),
            $rowTag ?? Generators::constant(null),
            $fields ?? Generators::constant([]),
            $lines ?? Generators::constant([]),
            $ignored ?? Generators::constant(null),
            $columns ?? Generators::constant([]),
            $assignments ?? Generators::constant([]),
            $bulk ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable>>
     */
    public static function forms(): iterable
    {
        yield 'LoadTable/55' => new LoadTableGenerator(
            format: LoadFormatGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat::Data]),
            lock: Generators::constant(null),
            input: new LoadInputGenerator(
                local: Generators::constant(false),
                source: LoadSourceGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile]),
                file: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('text'),
                    radix: Generators::constant(null)
                ),
                count: Generators::constant(null),
                primaryKeyOrder: Generators::constant(false),
                countWord: Generators::constant(null)
            ),
            duplicates: Generators::constant(null),
            table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                alias: Generators::constant(null),
                partitions: Generators::constant([]),
                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
            ),
            charset: Generators::constant(null),
            compression: Generators::constant(null),
            rowTag: Generators::constant(null),
            fields: Generators::constant([]),
            lines: Generators::constant([]),
            ignored: Generators::constant(null),
            columns: Generators::constant([]),
            assignments: Generators::constant([]),
            bulk: Generators::constant(null)
        );
        yield 'LoadTable/180' => new LoadTableGenerator(
            format: LoadFormatGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat::Data]),
            lock: Generators::constant(null),
            input: new LoadInputGenerator(
                local: Generators::constant(false),
                source: LoadSourceGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile]),
                file: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('text'),
                    radix: Generators::constant(null)
                ),
                count: Generators::constant(null),
                primaryKeyOrder: Generators::constant(false),
                countWord: Generators::constant(null)
            ),
            duplicates: Generators::constant(null),
            table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                alias: Generators::constant(null),
                partitions: Generators::constant([]),
                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
            ),
            charset: Generators::constant(null),
            compression: Generators::constant(null),
            rowTag: Generators::constant(null),
            fields: Generators::constant([]),
            lines: Generators::constant([]),
            ignored: Generators::constant(null),
            columns: Generators::constant([]),
            assignments: Generators::constant([]),
            bulk: new BulkOptionsGenerator(
                parallel: Generators::constant(null),
                memory: new \Tests\Generator\MySql\Statement\Literal\ByteSizeGenerator(
                    number: Generators::constant(null),
                    word: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('1K'),
                        quote: \Tests\Generator\Semantics\Statement\Identifier\NameQuoteGenerator::selecting([\SqlSemantics\Statement\Identifier\NameQuote::Bare])
                    )
                ),
                bulk: Generators::constant(false)
            )
        );
        yield 'LoadTable/376' => new LoadTableGenerator(
            format: LoadFormatGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat::Data]),
            lock: LoadLockGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadLock::Concurrent]),
            input: new LoadInputGenerator(
                local: Generators::constant(false),
                source: LoadSourceGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile]),
                file: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('text'),
                    radix: Generators::constant(null)
                ),
                count: Generators::constant(null),
                primaryKeyOrder: Generators::constant(false),
                countWord: Generators::constant(null)
            ),
            duplicates: Generators::constant(null),
            table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                alias: Generators::constant(null),
                partitions: Generators::constant([]),
                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
            ),
            charset: Generators::constant(null),
            compression: Generators::constant(null),
            rowTag: Generators::constant(null),
            fields: Generators::constant([]),
            lines: Generators::constant([]),
            ignored: Generators::constant(null),
            columns: Generators::constant([]),
            assignments: Generators::constant([]),
            bulk: Generators::constant(null)
        );
        yield 'LoadTable/377' => new LoadTableGenerator(
            format: LoadFormatGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat::Data]),
            lock: Generators::constant(null),
            input: new LoadInputGenerator(
                local: Generators::constant(false),
                source: LoadSourceGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile]),
                file: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('text'),
                    radix: Generators::constant(null)
                ),
                count: Generators::constant(null),
                primaryKeyOrder: Generators::constant(false),
                countWord: Generators::constant(null)
            ),
            duplicates: Generators::constant(null),
            table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                alias: Generators::constant(null),
                partitions: Generators::constant([]),
                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
            ),
            charset: Generators::constant(null),
            compression: Generators::constant(null),
            rowTag: Generators::constant(null),
            fields: \Tests\Generator\Operands::tuple(
                new FieldOptionGenerator(
                    kind: FieldOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind::Terminated]),
                    text: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('0f'),
                        radix: \Tests\Generator\MySql\Statement\Literal\RadixGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal])
                    )
                )
            ),
            lines: Generators::constant([]),
            ignored: Generators::constant(null),
            columns: Generators::constant([]),
            assignments: Generators::constant([]),
            bulk: Generators::constant(null)
        );
        yield 'LoadTable/378' => new LoadTableGenerator(
            format: LoadFormatGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat::Data]),
            lock: Generators::constant(null),
            input: new LoadInputGenerator(
                local: Generators::constant(false),
                source: LoadSourceGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile]),
                file: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('text'),
                    radix: Generators::constant(null)
                ),
                count: Generators::constant(null),
                primaryKeyOrder: Generators::constant(false),
                countWord: Generators::constant(null)
            ),
            duplicates: Generators::constant(null),
            table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                alias: Generators::constant(null),
                partitions: Generators::constant([]),
                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
            ),
            charset: Generators::constant(null),
            compression: Generators::constant(null),
            rowTag: Generators::constant(null),
            fields: Generators::constant([]),
            lines: \Tests\Generator\Operands::tuple(
                new LineOptionGenerator(
                    kind: LineOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOptionKind::Terminated]),
                    text: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('0f'),
                        radix: \Tests\Generator\MySql\Statement\Literal\RadixGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal])
                    )
                )
            ),
            ignored: Generators::constant(null),
            columns: Generators::constant([]),
            assignments: Generators::constant([]),
            bulk: Generators::constant(null)
        );
    }

}
