<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes Delete values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Delete>
 */
final class DeleteGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Delete> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\WithClause|null> $with Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption>> $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $where Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem>>|null $orderBy Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint>>|null $hints Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $with, ErisGenerator $options, ErisGenerator $table, ?ErisGenerator $where = null, ?ErisGenerator $orderBy = null, ?ErisGenerator $limit = null, ?ErisGenerator $hints = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Delete {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Delete(with: $parts[0], options: $parts[1], table: $parts[2], where: $parts[3], orderBy: $parts[4], limit: $parts[5], hints: $parts[6], statementInformation: $parts[7], inputSource: $parts[8]);
        }, \Tests\Generator\Operands::tuple(
            $with,
            $options,
            $table,
            $where ?? Generators::constant(null),
            $orderBy ?? Generators::constant([]),
            $limit ?? Generators::constant(null),
            $hints ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Delete>>
     */
    public static function forms(): iterable
    {
        yield 'Delete/29' => new DeleteGenerator(
            with: Generators::constant(null),
            options: Generators::constant([]),
            table: new WriteTargetGenerator(
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
            where: Generators::constant(null),
            orderBy: Generators::constant([]),
            limit: Generators::constant(null),
            hints: Generators::constant([])
        );
        yield 'Delete/353' => new DeleteGenerator(
            with: Generators::constant(null),
            options: Generators::constant([]),
            table: new WriteTargetGenerator(
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
            where: Generators::constant(null),
            orderBy: Generators::constant([]),
            limit: new \Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator(
                count: new \Tests\Generator\MySql\Statement\Query\Clause\ProgramVariableGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                ),
                offset: Generators::constant(null),
                spelling: Generators::constant(null)
            ),
            hints: Generators::constant([])
        );
        yield 'Delete/363' => new DeleteGenerator(
            with: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                DeleteOptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption::Quick])
            ),
            table: new WriteTargetGenerator(
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
            where: Generators::constant(null),
            orderBy: Generators::constant([]),
            limit: Generators::constant(null),
            hints: Generators::constant([])
        );
    }

    /**
     * Generates complete deletion conditions coupled to the tables they read.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int, int, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement}>
     */
    public static function constraints(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        return \Tests\Generator\Operands::bind(new \Tests\Generator\MySql\Statement\Table\CreateTableGenerator(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release)), static function (\SqlSemantics\Platform\MySql\Statement\Table\CreateTable $create) use ($release): ErisGenerator {
            $database = $create->name->schema ?? new \SqlSemantics\Statement\Identifier\Name('pbt');
            $profile = new \SqlSemantics\Contract\LanguageProfile($release);
            $missing = new \SqlSemantics\Contract\AnalysisContext($profile, [$database], [], true);
            $definition = \SqlSemantics\Facade\Semantics::fromStatement($missing, $create);
            $declared = new \SqlSemantics\Contract\AnalysisContext($profile, [$database], $definition->declarations(), true);
            $alias = new \SqlSemantics\Statement\Identifier\Name('generated_input');
            $target = new \SqlSemantics\Statement\Identifier\QualifiedName($alias);
            $table = new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(Generators::constant($create->name), Generators::constant($alias));
            $wild = new \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator();
            $form = new MultipleDeleteFormGenerator();
            $query = new \Tests\Generator\MySql\Statement\Query\SelectGenerator(Generators::constant([]), \Tests\Generator\Operands::vector(1, new \Tests\Generator\MySql\Statement\Query\StarGenerator()), $table);
            $derived = new \Tests\Generator\MySql\Statement\Relation\DerivedTableGenerator($query, Generators::constant($alias));
            $choices = [
                \Tests\Generator\Operands::tuple(new self(Generators::constant(null), Generators::constant([]), new WriteTargetGenerator(Generators::constant($create->name)), orderBy: \Tests\Generator\Operands::vector(1, new \Tests\Generator\MySql\Statement\Query\OrderItemGenerator(new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(1, 20)))))), Generators::constant(\MySqlMemory\Error\Family\QueryError::BadField)),
                \Tests\Generator\Operands::tuple(new MultipleDeleteGenerator(Generators::constant(null), Generators::constant([]), new Target\DeletionInputGenerator(\Tests\Generator\Operands::vector(1, $derived), Generators::constant([$target]), wildcards: \Tests\Generator\Operands::vector(1, $wild)), $form), Generators::constant(\MySqlMemory\Error\Family\QueryError::NonUpdatableTable)),
                \Tests\Generator\Operands::tuple(new MultipleDeleteGenerator(Generators::constant(null), Generators::constant([]), new Target\DeletionInputGenerator(\Tests\Generator\Operands::vector(1, $table), Generators::constant([$target, $target]), wildcards: \Tests\Generator\Operands::vector(2, $wild)), $form), Generators::constant(\MySqlMemory\Error\Family\QueryError::NonUniqueTable)),
                \Tests\Generator\Operands::tuple(new MultipleDeleteGenerator(Generators::constant(null), Generators::constant([]), new Target\DeletionInputGenerator(\Tests\Generator\Operands::vector(1, $table), Generators::constant([new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('absent_target'))]), wildcards: \Tests\Generator\Operands::vector(1, $wild)), $form), Generators::constant(\MySqlMemory\Error\Family\QueryError::UnknownTable)),
            ];
            return Generators::map(static function (array $parts) use ($definition, $missing, $declared): array {
                [$write, $condition] = $parts;
                $expected = match ($condition) {
                    \MySqlMemory\Error\Family\QueryError::BadField => \MySqlMemory\Error\Family\QueryError::BadField,
                    \MySqlMemory\Error\Family\QueryError::NonUpdatableTable => \MySqlMemory\Error\Family\QueryError::NonUpdatableTable,
                    \MySqlMemory\Error\Family\QueryError::NonUniqueTable => \MySqlMemory\Error\Family\QueryError::NonUniqueTable,
                    \MySqlMemory\Error\Family\QueryError::UnknownTable => \MySqlMemory\Error\Family\QueryError::UnknownTable,
                    default => throw new LogicException('A generated deletion declares one of its SQL operand conditions.'),
                };
                $before = in_array($expected, [\MySqlMemory\Error\Family\QueryError::NonUniqueTable, \MySqlMemory\Error\Family\QueryError::UnknownTable], true) ? $expected : \MySqlMemory\Error\Family\QueryError::NoSuchTable;
                return [$definition,
                    \SqlSemantics\Facade\Semantics::fromStatement($missing, $write),
                    \SqlSemantics\Facade\Semantics::fromStatement($declared, $write),
                    $before->value, $expected->value,
                    \SqlSemantics\Facade\Semantics::fromStatement($missing, new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain($write)),
                    \SqlSemantics\Facade\Semantics::fromStatement($declared, new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain($write))];
            }, \Tests\Generator\Operands::oneOf($choices));
        });
    }

    /**
     * Couples generated CREATE, INSERT and DELETE requests through one declared column.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function rows(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $definitions = new \Tests\Generator\MySql\Statement\Table\CreateTableGenerator(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release));
        return Generators::map(static fn (array $parts): array => self::sequence($release, ...$parts), \Tests\Generator\Operands::tuple($definitions, Generators::choose(-100, 100), Generators::choose(1, 20), Generators::bool(), new MultipleDeleteFormGenerator(), new \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator()));
    }

    /**
     * Publishes a dependency sequence from its generated declaration and bounded row operands.
     *
     * @return array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int}
     */
    public static function sequence(\SqlSemantics\Contract\GrammarRelease $release, \SqlSemantics\Platform\MySql\Statement\Table\CreateTable $create, int $first, int $offset, bool $multiple, \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm $form, \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords $wildcard): array
    {
        \SqlSemantics\Diagnostic\Check::input($first >= -100 && $first <= 100 && $offset >= 1 && $offset <= 20, 'Generated deletion rows use bounded distinct integer values.');
        $database = $create->name->schema ?? new \SqlSemantics\Statement\Identifier\Name('pbt');
        $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [$database], [], true);
        $definition = \SqlSemantics\Facade\Semantics::fromStatement($context, $create);
        $context = new \SqlSemantics\Contract\AnalysisContext($context->profile, [$database], $definition->declarations(), true);
        $column = $definition->declarations()[0]->columns[0]->name;
        $number = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) abs($first));
        $number = $first < 0 ? new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus, $number) : $number;
        $remaining = $first + $offset;
        $other = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) abs($remaining));
        $other = $remaining < 0 ? new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus, $other) : $other;
        $insert = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows(new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(false, null, false, new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget($create->name)), [new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow([$number]), new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow([$other])]);
        $predicate = new \SqlSemantics\Platform\MySql\Statement\Expression\Comparison(\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal, new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($column), $number);
        $delete = $multiple
            ? new \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete(null, [], new \SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput([new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference($create->name)], [$create->name], wildcards: [$wildcard]), $form, $predicate)
            : new \SqlSemantics\Platform\MySql\Statement\Dml\Delete(null, [], new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget($create->name), $predicate);
        return [$definition, \SqlSemantics\Facade\Semantics::fromStatement($context, $insert), \SqlSemantics\Facade\Semantics::fromStatement($context, $delete), $remaining];

    }

}
