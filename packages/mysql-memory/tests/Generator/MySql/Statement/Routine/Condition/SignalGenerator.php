<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Condition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use Tests\Generator\Composition;

/**
 * Composes Signal values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal>
 */
final class SignalGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal> */
    use Composition;

    /**
     * Generates complete Signal values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<M\Routine\Condition\Signal>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                $forms[] = self::operands(
                    new SqlStateGenerator(new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::constant('45000'))),
                    Generators::seq(new SignalItemGenerator(
                        ConditionItemNameGenerator::selecting([M\Routine\Condition\ConditionItemName::MessageText, M\Routine\Condition\ConditionItemName::MysqlErrno]),
                        Generators::oneOf(
                            \Tests\Generator\MySql\Statement\Variable\VariableAssignmentGenerator::numbers(),
                            new \Tests\Generator\MySql\Statement\Variable\SystemVariableGenerator(\Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::elements('missing_variable', 'autocommit'), Generators::constant(null))),
                        ),
                    )),
                );
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No Signal forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): M\Routine\Condition\Signal {
            if (!$statement instanceof M\Routine\Condition\Signal) {
                throw new LogicException('Publishing Signal must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<M\Routine\Condition\ConditionName|M\Routine\Condition\SqlState> $condition Generated constructor operand
     * @param ErisGenerator<list<M\Routine\Condition\SignalItem>>|null $items Generated constructor operand
     * @param ErisGenerator<M\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<M\Routine\Condition\Signal>
     */
    public static function operands(ErisGenerator $condition, ?ErisGenerator $items = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): M\Routine\Condition\Signal {
            return new M\Routine\Condition\Signal(condition: $parts[0], items: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple($condition, $items ?? Generators::constant([]), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<M\Routine\Condition\Signal>>
     */
    public static function forms(): iterable
    {
        yield 'Signal/121' => SignalGenerator::operands(
            condition: new ConditionNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            items: Generators::constant([])
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{GrammarRelease, ErisGenerator<M\Routine\Condition\Signal>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'Signal/additional/19' => [GrammarRelease::MySql847, SignalGenerator::operands(
            condition: new SqlStateGenerator(
                state: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('42S02'),
                    radix: Generators::constant(null)
                )
            ),
            items: Generators::constant([])
        )];
    }


    /**
     * Generates SIGNAL and RESIGNAL with condition, value and repeated-item errors.
     * Both top-level statements and stored bodies keep all operands during shrinking.
     *
     * @return ErisGenerator<array{M\Statement, int, string}>
     */
    public static function inputConditions(GrammarRelease $release, int $owner): ErisGenerator
    {
        return Generators::map(self::inputCondition(...), Generators::tuple(
            Generators::constant($release),
            new SqlStateGenerator(new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $value): string => 'bad' . $value, Generators::choose(0, 1000))), valueWord: new \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator()),
            \Tests\Generator\MySql\Statement\Literal\TemporalLiteralGenerator::invalid(),
            ConditionItemNameGenerator::selecting([M\Routine\Condition\ConditionItemName::ClassOrigin, M\Routine\Condition\ConditionItemName::SubclassOrigin, M\Routine\Condition\ConditionItemName::ConstraintCatalog, M\Routine\Condition\ConditionItemName::ConstraintSchema, M\Routine\Condition\ConditionItemName::ConstraintName, M\Routine\Condition\ConditionItemName::CatalogName, M\Routine\Condition\ConditionItemName::SchemaName, M\Routine\Condition\ConditionItemName::TableName, M\Routine\Condition\ConditionItemName::ColumnName, M\Routine\Condition\ConditionItemName::CursorName, M\Routine\Condition\ConditionItemName::MessageText, M\Routine\Condition\ConditionItemName::MysqlErrno]),
            Generators::choose(1, 1000),
            Generators::choose(0, 2),
            Generators::constant($owner),
            new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()
        ));
    }

    /**
     * Composes the generated instruction and its complete owning declaration before execution.
     *
     * @param array{GrammarRelease, M\Routine\Condition\SqlState, M\Literal\TemporalLiteral, M\Routine\Condition\ConditionItemName, int, int, int, \SqlSemantics\Statement\Identifier\Name} $parts
     * @return array{M\Statement, int, string}
     */
    public static function inputCondition(array $parts): array
    {
        [$release, $state, $temporal, $item, $number, $kind, $owner, $name] = $parts;
        $condition = $kind === 0 ? $state : new M\Routine\Condition\SqlState(new M\Literal\Text('45000'), valueWord: $state->valueWord);
        $items = [new M\Routine\Condition\SignalItem($item, new M\Literal\NumberLiteral((string) $number)), new M\Routine\Condition\SignalItem($item, $kind < 2 ? $temporal : new M\Literal\NumberLiteral((string) ($number + 1)))];
        $instruction = $owner % 2 === 0 ? new M\Routine\Condition\Signal($condition, $items) : new M\Routine\Condition\Resignal($condition, $items);
        $request = $instruction;
        if ($owner >= 2) {
            $body = $owner === 2 ? new M\Routine\Program\Block(statements: [$instruction]) : new M\Routine\Program\Block(declarations: [new M\Routine\Program\HandlerDeclaration(M\Routine\Program\HandlerAction::Exit, [new M\Routine\Condition\GeneralCondition(M\Routine\Condition\ConditionClass::SqlException)], $instruction)]);
            $request = new M\Routine\CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName($name), new M\Routine\ParameterList([]), $body);
        }
        $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')]);
        $message = match ($kind) {
            0 => "Bad SQLSTATE: '" . $state->state->value . "'",
            1 => 'Incorrect ' . ($temporal->form === M\Literal\TemporalForm::Timestamp ? 'DATETIME' : $temporal->form->value) . " value: '" . $temporal->text . "'",
            default => "Duplicate condition information item '" . $item->value . "'",
        };
        return [(new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $request), [1407, 1525, 1641][$kind], $message];
    }
}
