<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Explain;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes Explain values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain>
 */
final class ExplainGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain> */
    use Composition;

    /**
     * Generates complete Explain values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain>|null $operands A constrained form of this concrete result
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
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No Explain forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain) {
                throw new LogicException('Publishing Explain must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Explainable> $statement Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $format Generated constructor operand
     * @param ErisGenerator<bool>|null $analyze Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier|null>|null $modifier Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable|null>|null $into Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $database Generated constructor operand
     * @param ErisGenerator<bool>|null $emptyInto Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainVerb>|null $verb Generated command choice
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain>
     */
    public static function operands(ErisGenerator $statement, ?ErisGenerator $format = null, ?ErisGenerator $analyze = null, ?ErisGenerator $modifier = null, ?ErisGenerator $into = null, ?ErisGenerator $database = null, ?ErisGenerator $emptyInto = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $verb = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain(statement: $parts[0], format: $parts[1], analyze: $parts[2], modifier: $parts[3], into: $parts[4], database: $parts[5], emptyInto: $parts[6], statementInformation: $parts[7], inputSource: $parts[8], verb: $parts[9]);
        }, Generators::tuple($statement, $format ?? Generators::constant(null), $analyze ?? Generators::constant(false), $modifier ?? Generators::constant(null), $into ?? Generators::constant(null), $database ?? Generators::constant(null), $emptyInto ?? Generators::constant(false), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $verb ?? new ExplainVerbGenerator()));
    }
    /**
     * Generates refused report options around a complete, independent scalar query.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function refusedOptions(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $options = [['XML', false, 1791]];
        if (!in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744, \SqlSemantics\Contract\GrammarRelease::MySql8044], true)) {
            $options[] = [null, true, 6006];
            $options[] = ['TREE', true, 6007];
        }
        return Generators::bind(Generators::elements($options), static fn (array $option): ErisGenerator => self::refusal($release, $option));
    }

    /**
     * Composes a selected option case while retaining typed shrinking operands.
     *
     * @param array{string|null, bool, int} $option The report format, flags and expected error
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function refusal(\SqlSemantics\Contract\GrammarRelease $release, array $option): ErisGenerator
    {
        [$format, $into, $code] = $option;
        $query = Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral $number): \SqlSemantics\Platform\MySql\Statement\QueryRoot => new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($number)])), new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 65535))));
        $request = self::operands(
            $query,
            format: $format === null ? Generators::constant(null) : \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::elements($format, strtolower($format)), Generators::constant(null)),
            into: $into ? new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(\Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::map(static fn (int $id): string => 'plan_' . $id, Generators::choose(0, 32)), Generators::constant(null))) : Generators::constant(null)
        );
        return Generators::map(static fn (\SqlSemantics\Statement\AnalyzedStatement $statement): array => [$statement, $code], new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $request));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain>>
     */
    public static function forms(): iterable
    {
        yield 'Explain/368' => ExplainGenerator::operands(
            statement: new \Tests\Generator\MySql\Statement\ExplainableGenerator([\Tests\Generator\MySql\Statement\QueryRootGenerator::operands(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )])
            )]),
            format: Generators::constant(null),
            analyze: Generators::constant(true),
            modifier: Generators::constant(null),
            into: Generators::constant(null),
            database: Generators::constant(null),
            emptyInto: Generators::constant(false)
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'Explain/additional/85' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, ExplainGenerator::operands(
            statement: new \Tests\Generator\MySql\Statement\ExplainableGenerator([\Tests\Generator\MySql\Statement\QueryRootGenerator::operands(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                    options: Generators::constant([]),
                    items: Generators::tuple(
                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                            expression: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            alias: Generators::constant(null),
                            layout: Generators::constant(null),
                            mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                            spellingProfile: Generators::constant(null)
                        )])
                    ),
                    from: Generators::constant(null),
                    where: Generators::constant(null),
                    groupBy: Generators::constant(null),
                    having: Generators::constant(null),
                    windows: Generators::constant([]),
                    qualify: Generators::constant(null),
                    orderBy: Generators::constant([]),
                    limit: Generators::constant(null),
                    procedure: Generators::constant(null),
                    locking: Generators::constant([]),
                    into: Generators::constant(null),
                    intoPosition: Generators::constant(null),
                    late: Generators::constant(null),
                    hints: Generators::constant([])
                )])
            )]),
            format: Generators::constant(null),
            analyze: Generators::constant(false),
            modifier: ExplainModifierGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier::Extended]),
            into: Generators::constant(null),
            database: Generators::constant(null),
            emptyInto: Generators::constant(false)
        )];
    }

}
