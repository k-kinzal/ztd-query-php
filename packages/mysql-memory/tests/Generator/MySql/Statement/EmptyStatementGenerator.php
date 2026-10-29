<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes EmptyStatement values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\EmptyStatement>
 */
final class EmptyStatementGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\EmptyStatement> */
    use Composition;

    /**
     * Generates complete EmptyStatement values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\EmptyStatement>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            $forms[] = self::operands();
            $forms[] = Generators::map(
                static fn (\SqlSemantics\Statement\Script $script): \SqlSemantics\Platform\MySql\Statement\EmptyStatement => new \SqlSemantics\Platform\MySql\Statement\EmptyStatement(inputSource: $script->inputSource),
                new \Tests\Generator\Semantics\Statement\ScriptGenerator(Generators::constant([])),
            );
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\EmptyStatement {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\EmptyStatement) {
                throw new LogicException('Publishing EmptyStatement must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\EmptyStatement>
     */
    public static function operands(?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\EmptyStatement {
            return new \SqlSemantics\Platform\MySql\Statement\EmptyStatement(statementInformation: $parts[0], inputSource: $parts[1]);
        }, Generators::tuple($statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
}
