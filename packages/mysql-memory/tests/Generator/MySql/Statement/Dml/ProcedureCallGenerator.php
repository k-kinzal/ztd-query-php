<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes ProcedureCall values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall>
 */
final class ProcedureCallGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall> */
    use Composition;

    /**
     * Generates complete ProcedureCall values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall>|null $operands A constrained form of this concrete result
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

            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                $forms[] = Generators::map(static fn (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall => $parts[1], \Tests\Generator\MySql\Statement\Routine\Program\ProgramRowGenerator::invocation());
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No ProcedureCall forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall) {
                throw new LogicException('Publishing ProcedureCall must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $procedure Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar>>|null $arguments Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $parentheses Generated parentheses compatible with the arguments
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall>
     */
    public static function operands(ErisGenerator $procedure, ?ErisGenerator $arguments = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $parentheses = null): ErisGenerator
    {
        return Generators::bind($arguments ?? Generators::constant([]), static function (array $values) use ($procedure, $statementInformation, $inputSource, $parentheses): ErisGenerator {
            return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall {
                return new \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall(procedure: $parts[0], arguments: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], parentheses: $parts[4]);
            }, Generators::tuple($procedure, Generators::constant($values), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $parentheses ?? ($values === [] ? new \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator() : Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written))));
        });
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall>>
     */
    public static function forms(): iterable
    {
        yield 'ProcedureCall/16' => ProcedureCallGenerator::operands(
            procedure: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            arguments: Generators::constant([])
        );
    }

}
