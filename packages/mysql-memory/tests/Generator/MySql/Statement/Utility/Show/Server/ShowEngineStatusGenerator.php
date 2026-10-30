<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Server;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes ShowEngineStatus values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus>
 */
final class ShowEngineStatusGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus> */
    use Composition;

    /**
     * Generates complete ShowEngineStatus values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No ShowEngineStatus forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus) {
                throw new LogicException('Publishing ShowEngineStatus must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<?\SqlSemantics\Statement\Identifier\Name>|null $engine Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus>
     */
    public static function operands(?ErisGenerator $engine = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus(engine: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple($engine ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus>>
     */
    public static function forms(): iterable
    {
        yield 'all, known and unknown engines' => self::operands(Generators::oneOf(Generators::constant(null), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::elements('InnoDB', 'MyISAM', 'MEMORY', 'missing_engine'), Generators::constant(null))));
    }

}
