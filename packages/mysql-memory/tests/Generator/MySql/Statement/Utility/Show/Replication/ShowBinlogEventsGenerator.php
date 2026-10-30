<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Replication;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes ShowBinlogEvents values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents>
 */
final class ShowBinlogEventsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents> */
    use Composition;

    /**
     * Generates complete ShowBinlogEvents values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents>|null $operands A constrained form of this concrete result
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

            if ($forms === []) {
                throw new InvalidArgumentException('No ShowBinlogEvents forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents) {
                throw new LogicException('Publishing ShowBinlogEvents must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Literal\Text>|null $file Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Literal\Numeral>|null $position Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Query\Limit>|null $limit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents>
     */
    public static function operands(?ErisGenerator $file = null, ?ErisGenerator $position = null, ?ErisGenerator $limit = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents(file: $parts[0], position: $parts[1], limit: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple($file ?? Generators::constant(null), $position ?? Generators::constant(null), $limit ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents>>
     */
    public static function forms(): iterable
    {
        $number = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 1024)));
        $limit = Generators::oneOf(Generators::constant(null), new \Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator($number), new \Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator($number, $number, new \Tests\Generator\MySql\Statement\Query\Clause\OffsetSpellingGenerator()));
        $file = Generators::oneOf(Generators::constant(null), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $number): string => 'mysql-bin.' . $number, Generators::choose(0, 999))));
        $position = Generators::oneOf(Generators::constant(null), new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 1024))));
        yield 'log file, position and row limits' => self::operands($file, $position, $limit);
    }

}
