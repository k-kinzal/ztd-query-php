<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Log;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes PurgeLogsBefore values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore>
 */
final class PurgeLogsBeforeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore> */
    use Composition;

    /**
     * Generates complete PurgeLogsBefore values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release->database() === 'mysql') {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
                $forms[] = self::operands(self::moments(), masterLogs: str_starts_with($release->value, 'mysql-9.') ? Generators::constant(false) : Generators::bool());
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No PurgeLogsBefore forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore) {
                throw new LogicException('Publishing PurgeLogsBefore must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }

    /**
     * Varies cutoff values, invalid dates, Unix boundaries and fractional seconds.
     *
     * @return ErisGenerator<\SqlSemantics\Statement\Scalar>
     */
    public static function moments(): ErisGenerator
    {
        $dates = Generators::map(static fn (array $parts): string => sprintf('%04d-%02d-%02d %02d:%02d:%02d.%06d', ...$parts), Generators::tuple(Generators::choose(1969, 3002), Generators::choose(0, 13), Generators::choose(0, 32), Generators::choose(0, 24), Generators::choose(0, 60), Generators::choose(0, 60), Generators::choose(0, 999999)));
        return Generators::oneOf(
            new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(strings: Generators::oneOf($dates, Generators::elements('', 'garbage', '0000-00-00', '1970-01-01 00:00:01', '2038-01-19 03:14:08', '9999-12-31 23:59:59'))),
            new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::oneOf(Generators::elements('0', '1', '20240101', '20240101000000.5'), Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 99999999)))),
            new \Tests\Generator\MySql\Statement\Literal\NullLiteralGenerator(),
        );
    }

    /**
     * Composes log rotations and a complete dependent cutoff request.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush, \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore}>
     */
    public static function withRotatedLogs(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $item = new \Tests\Generator\MySql\Statement\Server\Flush\FlushItemGenerator(\Tests\Generator\MySql\Statement\Server\Flush\FlushOptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption::BinaryLogs]));
        $items = Generators::bind(Generators::choose(1, 4), static fn (int $count): ErisGenerator => Generators::vector($count, $item));
        return Generators::tuple(
            new \Tests\Generator\MySql\Statement\Server\Flush\FlushGenerator($release, \Tests\Generator\MySql\Statement\Server\Flush\FlushGenerator::operands(Generators::constant(null), $items)),
            new self($release, self::operands(self::moments(), masterLogs: Generators::constant(false))),
        );
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $moment Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore>
     * @param ErisGenerator<bool>|null $masterLogs Generated legacy word choice
     */
    public static function operands(ErisGenerator $moment, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $masterLogs = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore(moment: $parts[0], statementInformation: $parts[1], inputSource: $parts[2], masterLogs: $parts[3]);
        }, Generators::tuple($moment, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $masterLogs ?? Generators::bool()));
    }
    /**
     * Composes a current-user cutoff from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore>>
     */
    public static function forms(): iterable
    {
        yield 'PurgeLogsBefore/374' => PurgeLogsBeforeGenerator::operands(
            masterLogs: Generators::constant(false),
            moment: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                arguments: Generators::constant([]),
                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                            )])
                        )])
                    )])
                )])
            )])
        );
    }

}
