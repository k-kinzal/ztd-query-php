<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes InsertInto values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto>
 */
final class InsertIntoGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $replace Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertPriority|null> $priority Generated constructor operand
     * @param ErisGenerator<bool> $ignore Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Insert\ColumnList|null>|null $columns Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint>>|null $hints Generated constructor operand
     * @param ErisGenerator<bool>|null $intoWritten Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $replace, ErisGenerator $priority, ErisGenerator $ignore, ErisGenerator $table, ?ErisGenerator $columns = null, ?ErisGenerator $hints = null, ?ErisGenerator $intoWritten = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(replace: $parts[0], priority: $parts[1], ignore: $parts[2], table: $parts[3], columns: $parts[4], hints: $parts[5], intoWritten: $parts[6], inputSource: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $replace,
            $priority,
            $ignore,
            $table,
            $columns ?? Generators::constant(null),
            $hints ?? Generators::constant([]),
            $intoWritten ?? Generators::constant(true),
            $inputSource ?? Generators::constant(null)
        ));
    }

    /**
     * Composes immediate write constraints with the declaration they depend on.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement}>
     */
    public static function constraints(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $definitions = new \Tests\Generator\MySql\Statement\Table\CreateTableGenerator(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release));
        return \Tests\Generator\Operands::bind($definitions, static function (\SqlSemantics\Platform\MySql\Statement\Table\CreateTable $create) use ($release): ErisGenerator {
            $database = $create->name->schema ?? new \SqlSemantics\Statement\Identifier\Name('pbt');
            $profile = new \SqlSemantics\Contract\LanguageProfile($release);
            $missing = new \SqlSemantics\Contract\AnalysisContext($profile, [$database], [], true);
            $definition = \SqlSemantics\Facade\Semantics::fromStatement($missing, $create);
            $declared = new \SqlSemantics\Contract\AnalysisContext($profile, [$database], $definition->declarations(), true);
            $column = $definition->declarations()[0]->columns[0]->name;
            $numbers = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 100)));
            $head = new self(Generators::constant(false), Generators::constant(null), Generators::constant(false), new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(Generators::constant($create->name)), intoWritten: Generators::bool());
            $row = new \Tests\Generator\MySql\Statement\Expression\RowGenerator(\Tests\Generator\Operands::tuple(Generators::constant(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($column)), $numbers));
            $assignments = Generators::vector(1, new \Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredColumnAssignmentGenerator(Generators::constant(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($column)), $row));
            $scalar = new InsertedRowGenerator(Generators::vector(1, $numbers));
            $selection = new \Tests\Generator\MySql\Statement\Query\SelectGenerator(Generators::constant([]), Generators::vector(1, new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator($numbers)));
            $choices = [
                \Tests\Generator\Operands::tuple(new InsertRowsGenerator($head, \Tests\Generator\Operands::tuple($scalar, new InsertedRowGenerator(Generators::vector(2, $numbers))), keyword: new ValuesKeywordGenerator()), Generators::constant(1136)),
                \Tests\Generator\Operands::tuple(new InsertRowsGenerator($head, Generators::vector(1, new \Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertedRowGenerator(Generators::vector(1, $row))), keyword: new ValuesKeywordGenerator()), Generators::constant(1241)),
                \Tests\Generator\Operands::tuple(new InsertRowsGenerator($head, Generators::vector(1, $scalar), onDuplicate: $assignments, keyword: new ValuesKeywordGenerator()), Generators::constant(1241)),
                \Tests\Generator\Operands::tuple(new InsertSetGenerator($head, $assignments), Generators::constant(1241)),
                \Tests\Generator\Operands::tuple(new InsertQueryGenerator($head, new \Tests\Generator\MySql\Statement\Query\SelectGenerator(Generators::constant([]), Generators::vector(1, new \Tests\Generator\MySql\Statement\Query\StarGenerator()))), Generators::constant(1096)),
                \Tests\Generator\Operands::tuple(new InsertQueryGenerator($head, $selection, $assignments), Generators::constant(1241)),
            ];
            if (!in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
                $choices[] = \Tests\Generator\Operands::tuple(new InsertQueryGenerator($head, new \Tests\Generator\MySql\Statement\Query\ValuesQueryGenerator(Generators::vector(1, new \Tests\Generator\MySql\Statement\Query\ValueRowGenerator(Generators::vector(1, \Tests\Generator\Operands::oneOf([$numbers, Generators::constant(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($column))]))))), $assignments), Generators::constant(1241));
                $choices[] = \Tests\Generator\Operands::tuple(new InsertQueryGenerator($head, new \Tests\Generator\MySql\Statement\Query\ValuesQueryGenerator(Generators::vector(1, new \Tests\Generator\MySql\Statement\Query\ValueRowGenerator(Generators::vector(2, $numbers)))), $assignments), Generators::constant(1136));

            }
            return Generators::map(
                static function (array $parts) use ($definition, $missing, $declared): array {
                    [$write, $code] = $parts;
                    $expected = match ($code) {
                        1096 => \MySqlMemory\Error\Family\QueryError::NoTablesUsed,
                        1136 => \MySqlMemory\Error\Family\QueryError::WrongValueCountOnRow,
                        1241 => \MySqlMemory\Error\Family\QueryError::OperandColumns,
                        default => throw new LogicException('A generated write constraint declares a supported SQL outcome.'),
                    };
                    return [
                        $definition,
                        \SqlSemantics\Facade\Semantics::fromStatement($missing, $write),
                        \SqlSemantics\Facade\Semantics::fromStatement($declared, $write),
                        $expected->value,
                        \SqlSemantics\Facade\Semantics::fromStatement($missing, new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain($write)),
                        \SqlSemantics\Facade\Semantics::fromStatement($declared, new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain($write)),
                    ];
                },
                \Tests\Generator\Operands::oneOf($choices)
            );
        });
    }

}
