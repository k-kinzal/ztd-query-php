<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes ShowProcedureStatus values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus>
 */
final class ShowProcedureStatusGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus> */
    use Composition;

    /**
     * Generates complete ShowProcedureStatus values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No ShowProcedureStatus forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus) {
                throw new LogicException('Publishing ShowProcedureStatus must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike|\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere|null>|null $filter Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus>
     */
    public static function operands(?ErisGenerator $filter = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus(filter: $parts[0], relationInformation: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple($filter ?? Generators::constant(null), $relationInformation ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus>>
     */
    public static function forms(): iterable
    {
        $pattern = new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::oneOf(Generators::elements('', '%', '_', 'utf8mb4%', 'missing%'), Generators::map(static fn (int $number): string => 'name_' . $number . '%', Generators::choose(0, 999))));
        yield 'optional name pattern' => self::operands(Generators::oneOf(Generators::constant(null), new \Tests\Generator\MySql\Statement\Utility\Show\ShowLikeGenerator($pattern)));
    }

}
