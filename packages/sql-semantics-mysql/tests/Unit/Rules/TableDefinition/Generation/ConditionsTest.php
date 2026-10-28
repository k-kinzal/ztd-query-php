<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\Conditions;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Conditions::class)]
#[Medium]
final class ConditionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerOperands(): iterable
    {
        foreach (['USER()', '@v', '@@missing', '(SELECT 1)', 'ROW(1, 2)', '?'] as $expression) {
            foreach (['CREATE TABLE t (a INT, g INT GENERATED ALWAYS AS (%s) STORED)', 'ALTER TABLE t ADD g INT AS (%s)', 'ALTER TABLE t MODIFY g INT AS (%s)', 'ALTER TABLE t CHANGE a g INT AS (%s)'] as $form) {
                $sql = sprintf($form, $expression);
                yield $sql => [$sql, $expression];
            }
        }
    }

    #[DataProvider('providerOperands')]
    public function testDeriveRetainsEveryGenerationOperand(string $sql, string $expression): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze($sql);
        $conditions = array_values(array_filter($statement->diagnostics(), static fn ($problem): bool => $problem instanceof M\Table\Problem\Generation\RefusedOperand));
        self::assertCount(1, $conditions);
        self::assertSame('g', $conditions[0]->column->value);
        self::assertNull($conditions[0]->expression);
        self::assertStringContainsString($expression, $statement->toString());
    }

    public function testProblemKeepsInvocationTimeConditionsSeparate(): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Call\KeywordCall(M\Call\KeywordFunction::User, [])));
        self::assertNull((new Conditions())->problem($column, GrammarRelease::MySql5744));
    }

    public function testDerivePublishesUnknownVariableBeforeTheGenerationRole(): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\GeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Variable\SystemVariable(new Name('missing'))));
        $derivation = new Derivation(new AnalysisContext(new LanguageProfile(GrammarRelease::MySql847), [new Name('test')]));
        (new Conditions())->derive($column, $derivation, $derivation->environment());
        $information = (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation);
        self::assertInstanceOf(M\Variable\Problem\UnknownSystemVariable::class, $information->diagnostics[0]);
        self::assertInstanceOf(M\Table\Problem\Generation\RefusedOperand::class, $information->diagnostics[1]);
        self::assertInstanceOf(M\Notice\ParseFailure::class, $information->warnings[0]);
        self::assertSame($information->diagnostics[0], $information->warnings[0]->problem);
    }
}
