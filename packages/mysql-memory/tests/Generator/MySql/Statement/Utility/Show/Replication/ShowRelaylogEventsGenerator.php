<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Replication;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes ShowRelaylogEvents values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents>
 */
final class ShowRelaylogEventsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents> */
    use Composition;

    /**
     * Generates complete ShowRelaylogEvents values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No ShowRelaylogEvents forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents) {
                throw new LogicException('Publishing ShowRelaylogEvents must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Literal\Text>|null $file Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Literal\Numeral>|null $position Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Query\Limit>|null $limit Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Literal\Text>|null $channel Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents>
     */
    public static function operands(?ErisGenerator $file = null, ?ErisGenerator $position = null, ?ErisGenerator $limit = null, ?ErisGenerator $channel = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents(file: $parts[0], position: $parts[1], limit: $parts[2], channel: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, Generators::tuple($file ?? Generators::constant(null), $position ?? Generators::constant(null), $limit ?? Generators::constant(null), $channel ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents>>
     */
    public static function forms(): iterable
    {
        yield 'ShowRelaylogEvents/111' => ShowRelaylogEventsGenerator::operands(
            file: Generators::constant(null),
            position: Generators::constant(null),
            limit: Generators::constant(null),
            channel: Generators::constant(null)
        );
    }

}
