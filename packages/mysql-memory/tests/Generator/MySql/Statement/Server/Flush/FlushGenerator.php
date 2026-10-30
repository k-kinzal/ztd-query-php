<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Flush;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption;
use Tests\Generator\Composition;

/**
 * Composes Flush values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush>
 */
final class FlushGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush> */
    use Composition;

    /**
     * Generates complete Flush values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release->database() === 'mysql') {
                foreach (self::forms($release) as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No Flush forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush) {
                throw new LogicException('Publishing Flush must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption|null> $binlog Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushItem>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush>
     */
    public static function operands(ErisGenerator $binlog, ErisGenerator $items, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush(binlog: $parts[0], items: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple($binlog, $items, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes nonempty option lists, including repeated options and independent relay channels.
     * Only options and channel syntax available in the selected grammar are generated.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush>>
     */
    public static function forms(GrammarRelease $release = GrammarRelease::MySql847): iterable
    {
        $options = [FlushOption::ErrorLogs, FlushOption::EngineLogs, FlushOption::GeneralLogs, FlushOption::SlowLogs, FlushOption::BinaryLogs, FlushOption::Privileges, FlushOption::Logs, FlushOption::Status, FlushOption::Resources];
        if ($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) {
            array_push($options, FlushOption::QueryCache, FlushOption::DesKeyFile);
        }
        if ($release !== GrammarRelease::MySql5651) {
            $options[] = FlushOption::OptimizerCosts;
        }
        if (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820], true)) {
            $options[] = FlushOption::Hosts;
        }
        $channel = $release === GrammarRelease::MySql5651 ? Generators::constant(null) : Generators::oneOf(
            Generators::constant(null),
            new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::constant('')),
            new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $value): string => 'channel_' . $value, Generators::choose(0, 20)))
        );
        $item = Generators::oneOf(
            new FlushItemGenerator(FlushOptionGenerator::selecting($options)),
            new FlushItemGenerator(FlushOptionGenerator::selecting([FlushOption::RelayLogs]), $channel)
        );
        yield 'option lists' => FlushGenerator::operands(
            binlog: \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            items: Generators::bind(Generators::choose(1, 6), static fn (int $count): ErisGenerator => Generators::vector($count, $item))
        );
    }

}
