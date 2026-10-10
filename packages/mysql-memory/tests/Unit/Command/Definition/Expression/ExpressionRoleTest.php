<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Expression;

use MySqlMemory\Command\Definition\Expression\ExpressionRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpressionRole::class)]
#[Small]
final class ExpressionRoleTest extends TestCase
{
    public function testFunctionAnswersTheErrorOfEachRole(): void
    {
        self::assertSame(
            [[3763, "Expression of generated column 'b' contains a disallowed function: rand."], [3770, "Default value expression of column 'b' contains a disallowed function: sleep."], [3814, "An expression of a check constraint 't_chk_1' contains disallowed function: now."]],
            [[ExpressionRole::Generated->function('b', 'rand')->getCode(), ExpressionRole::Generated->function('b', 'rand')->getMessage()], [ExpressionRole::Default->function('b', 'sleep')->getCode(), ExpressionRole::Default->function('b', 'sleep')->getMessage()], [ExpressionRole::Check->function('t_chk_1', 'now')->getCode(), ExpressionRole::Check->function('t_chk_1', 'now')->getMessage()]],
        );
    }

    public function testSubqueryAnswersTheErrorOfEachRole(): void
    {
        self::assertSame([3102, 3769, 3815], [ExpressionRole::Generated->subquery('b')->getCode(), ExpressionRole::Default->subquery('b')->getCode(), ExpressionRole::Check->subquery('c')->getCode()]);
    }

    public function testVariableAnswersTheErrorOfEachRole(): void
    {
        self::assertSame([3772, 3772, 3816], [ExpressionRole::Generated->variable('b')->getCode(), ExpressionRole::Default->variable('b')->getCode(), ExpressionRole::Check->variable('c')->getCode()]);
    }

    public function testRefusedAnswersTheFunctionsEachRoleRefuses(): void
    {
        self::assertSame(
            [['now', 'sysdate', 'version()'], [null, null, 'version()'], ['now', null, 'version()']],
            array_map(static fn (ExpressionRole $role): array => [$role->refused()['now'] ?? null, $role->refused()['sysdate'] ?? null, $role->refused()['version'] ?? null], [ExpressionRole::Generated, ExpressionRole::Default, ExpressionRole::Check]),
        );
    }
}
