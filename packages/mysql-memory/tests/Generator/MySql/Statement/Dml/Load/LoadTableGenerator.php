<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Load;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete LoadTable values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if (str_starts_with($release->value, 'mysql-')) {
                $forms[] = self::operands(
                    new LoadFormatGenerator(),
                    Generators::constant(null),
                    new LoadInputGenerator(Generators::bool(), Generators::constant(\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::elements('generated-input', '/var/lib/mysql-files/missing'))),
                    Generators::constant(null),
                    new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator())),
                );
            }
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No LoadTable forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable) {
                throw new LogicException('Publishing LoadTable must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
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
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Name\ColumnUse|\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable>|null>|null $columns Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment>>|null $assignments Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions|null>|null $bulk Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $ignoreRows The generated written alternative
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm>|null $charsetForm The written character-set introducer
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable>
     */
    public static function operands(ErisGenerator $format, ErisGenerator $lock, ErisGenerator $input, ErisGenerator $duplicates, ErisGenerator $table, ?ErisGenerator $charset = null, ?ErisGenerator $compression = null, ?ErisGenerator $rowTag = null, ?ErisGenerator $fields = null, ?ErisGenerator $lines = null, ?ErisGenerator $ignored = null, ?ErisGenerator $columns = null, ?ErisGenerator $assignments = null, ?ErisGenerator $bulk = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $ignoreRows = null, ?ErisGenerator $charsetForm = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable(format: $parts[0], lock: $parts[1], input: $parts[2], duplicates: $parts[3], table: $parts[4], charset: $parts[5], compression: $parts[6], rowTag: $parts[7], fields: $parts[8], lines: $parts[9], ignored: $parts[10], columns: $parts[11], assignments: $parts[12], bulk: $parts[13], statementInformation: $parts[14], inputSource: $parts[15], ignoreRows: $parts[16] && $parts[10] !== null, charsetForm: $parts[17]);
        }, Generators::tuple($format, $lock, $input, $duplicates, $table, $charset ?? Generators::constant(null), $compression ?? Generators::constant(null), $rowTag ?? Generators::constant(null), $fields ?? Generators::constant([]), $lines ?? Generators::constant([]), $ignored ?? Generators::constant(null), $columns ?? Generators::constant(null), $assignments ?? Generators::constant([]), $bulk ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $ignoreRows ?? Generators::bool(), $charsetForm ?? \Tests\Generator\MySql\Statement\Type\Kind\CharsetFormGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Named, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::CharacterSet, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::CharSet])));
    }
    /**
     * Composes missing-table and declared-table requests with the same operand constraint.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function constraints(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $definitions = \Tests\Generator\MySql\Statement\Table\CreateTableGenerator::operands(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release));
        return Generators::bind($definitions, static function (\SqlSemantics\Platform\MySql\Statement\Table\CreateTable $create) use ($release): ErisGenerator {
            return Generators::bind(Generators::tuple(Generators::choose(0, 100), Generators::bool()), static function (array $parts) use ($create, $release): ErisGenerator {
                [$number, $fixed] = $parts;
                $database = $create->name->schema ?? new \SqlSemantics\Statement\Identifier\Name('pbt');
                $profile = new \SqlSemantics\Contract\LanguageProfile($release);
                $missing = new \SqlSemantics\Contract\AnalysisContext($profile, [$database], [], true);
                $definition = \SqlSemantics\Facade\Semantics::fromStatement($missing, $create);
                $declared = new \SqlSemantics\Contract\AnalysisContext($profile, [$database], $definition->declarations(), true);
                $column = $definition->declarations()[0]->columns[0]->name;
                $assignments = $fixed ? Generators::constant([]) : Generators::vector(1, new \Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredColumnAssignmentGenerator(
                    Generators::constant(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($column)),
                    Generators::constant(new \SqlSemantics\Platform\MySql\Statement\Expression\Row([
                        new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($column),
                        new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) $number),
                    ])),
                ));
                $loads = self::operands(
                    Generators::constant(\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat::Data),
                    Generators::constant(null),
                    new LoadInputGenerator(Generators::constant(false), Generators::constant(\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile), Generators::constant(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('generated-input'))),
                    Generators::constant(null),
                    Generators::constant(new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget($create->name)),
                    fields: $fixed ? Generators::vector(1, new FieldOptionGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind::Terminated), Generators::constant(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('')))) : Generators::constant([]),
                    columns: $fixed ? Generators::constant([new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('input'))]) : Generators::constant(null),
                    assignments: $assignments,
                );
                return Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable $load): array => [
                    $definition,
                    \SqlSemantics\Facade\Semantics::fromStatement($missing, $load),
                    \SqlSemantics\Facade\Semantics::fromStatement($declared, $load),
                    $fixed ? 1409 : 1241,
                ], $loads);
            });
        });
    }

    /**
     * Composes a declaration, its file input and all-column LOAD forms before execution.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, list<list<int>>, string}>
     */
    public static function imports(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $definitions = \Tests\Generator\MySql\Statement\Table\CreateTableGenerator::operands(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release));
        return Generators::bind($definitions, static function (\SqlSemantics\Platform\MySql\Statement\Table\CreateTable $create) use ($release): ErisGenerator {
            return Generators::map(static fn (array $parts): array => self::import($create, $parts[0], $parts[1], $parts[2], $release), Generators::tuple(Generators::vector(3, Generators::choose(-10000, 10000)), Generators::bool(), new LoadFormatGenerator()));
        });
    }

    /**
     * Binds one generated file to its declaration and retains the expected rows.
     *
     * @param list<int> $rows The generated input field values
     * @return array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, list<list<int>>, string}
     */
    public static function import(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable $create, array $rows, bool $written, \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat $format, \SqlSemantics\Contract\GrammarRelease $release): array
    {
        $database = $create->name->schema ?? new \SqlSemantics\Statement\Identifier\Name('pbt');
        $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [$database]);
        $publication = new \SqlSemantics\Platform\MySql\Construction\Publication();
        $definition = $publication->publish($context, $create);
        $context = new \SqlSemantics\Contract\AnalysisContext($context->profile, [$database], $definition->declarations());
        $load = new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable($format, null, new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput(true, \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('generated-input')), null, new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget($create->name), columns: $written ? [] : null);
        $name = $definition->declarations()[0]->columns[0]->name->value;
        $contents = $format === \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat::Data
            ? implode("\n", $rows) . "\n"
            : '<rows>' . implode('', array_map(static fn (int $value): string => '<row><field name="' . htmlspecialchars($name, ENT_QUOTES | ENT_XML1, 'UTF-8') . '">' . $value . '</field></row>', $rows)) . '</rows>';
        $expected = [];
        foreach ($rows as $value) {
            $expected[] = [$value];
        }
        return [$definition, $publication->publish($context, $load), $expected, $contents];
    }

    /**
     * Composes a successful local import, its database, table, write lock and matching file.
     * Shrinking the existing import generator keeps column names and records related.
     * The executing connection must enable local loading and supply the generated file.
     *
     * @return ErisGenerator<array{list<\SqlSemantics\Statement\AnalyzedStatement>, string}>
     */
    public static function withPrerequisites(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $sequence = static function (\SqlSemantics\Statement\AnalyzedStatement $create, \SqlSemantics\Statement\AnalyzedStatement $load, string $contents) use ($release): ErisGenerator {
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')], $create->declarations());
            $database = new \Tests\Generator\MySql\Statement\Server\Database\CreateDatabaseGenerator($release, \Tests\Generator\MySql\Statement\Server\Database\CreateDatabaseGenerator::operands(Generators::constant(true), Generators::constant($create->declarations()[0]->name->schema ?? new \SqlSemantics\Statement\Identifier\Name('pbt'))));
            $lock = new \Tests\Generator\MySql\Statement\Server\Lock\TableLockGenerator(Generators::constant($create->declarations()[0]->name), Generators::constant(null), Generators::constant(\SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode::Write));
            $combine = static fn (\SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase $database, \SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables $locked): array => [[$database, $create, $locked, $load], $contents];
            return Generators::map(static fn (array $prerequisites): array => $combine(...$prerequisites), Generators::tuple($database, new \Tests\Generator\MySql\Statement\Server\Lock\LockTablesGenerator($release, \Tests\Generator\MySql\Statement\Server\Lock\LockTablesGenerator::operands(Generators::tuple($lock)), Generators::constant($context))));
        };
        return Generators::bind(self::imports($release), static fn (array $input): ErisGenerator => $sequence($input[0], $input[1], $input[3]));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable>>
     */
    public static function forms(): iterable
    {
        yield 'LoadTable/55' => LoadTableGenerator::operands(
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
            columns: Generators::elements([null, []]),
            assignments: Generators::constant([]),
            bulk: Generators::constant(null)
        );
        yield 'LoadTable/180' => LoadTableGenerator::operands(
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
            columns: Generators::elements([null, []]),
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
        yield 'LoadTable/376' => LoadTableGenerator::operands(
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
            columns: Generators::elements([null, []]),
            assignments: Generators::constant([]),
            bulk: Generators::constant(null)
        );
        yield 'LoadTable/377' => LoadTableGenerator::operands(
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
            fields: Generators::tuple(
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
            columns: Generators::elements([null, []]),
            assignments: Generators::constant([]),
            bulk: Generators::constant(null)
        );
        yield 'LoadTable/378' => LoadTableGenerator::operands(
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
            lines: Generators::tuple(
                new LineOptionGenerator(
                    kind: LineOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOptionKind::Terminated]),
                    text: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('0f'),
                        radix: \Tests\Generator\MySql\Statement\Literal\RadixGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal])
                    )
                )
            ),
            ignored: Generators::constant(null),
            columns: Generators::elements([null, []]),
            assignments: Generators::constant([]),
            bulk: Generators::constant(null)
        );
    }

}
