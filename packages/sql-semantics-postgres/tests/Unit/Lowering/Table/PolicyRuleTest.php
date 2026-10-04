<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\PolicyRule::class)]
#[Medium]
final class PolicyRuleTest extends TestCase
{
    public function testCreateLowersTheMode(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE POLICY p ON t AS restrictive');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\PolicyRule($lowering))->create($tree->find('CreatePolicyStmt')[0]);
        self::assertSame('restrictive', $value->mode?->value);
    }

    public function testAlterLowersTheRoles(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER POLICY p ON t TO a, b');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\PolicyRule($lowering))->alter($tree->find('AlterPolicyStmt')[0]);
        self::assertSame(2, count($value->roles));
    }

    public function testCommandLowersDelete(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE POLICY p ON t FOR DELETE');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\PolicyRule($lowering))->command($tree->find('row_security_cmd')[0]);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Table\Policy\PolicyCommand::Delete, $value);
    }

    public function testRolesIsEmptyWhenNotWritten(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE POLICY p ON t');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\PolicyRule($lowering))->roles($tree->find('RowSecurityDefaultToRole')[0]);
        self::assertSame([
        ], $value);
    }

    public function testExpressionLowersWithCheck(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE POLICY p ON t WITH CHECK (true)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\PolicyRule($lowering))->expression($tree->find('RowSecurityOptionalWithCheck')[0]);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\Literal\\BooleanLiteral', get_debug_type($value));
    }

    public function testCreateRejectsAnUnrecognizedMode(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE POLICY p ON t AS "select"');
        $this->expectExceptionMessage('unrecognized row security option "select"');
        (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\PolicyRule($lowering))->create($tree->find('CreatePolicyStmt')[0]);
    }
}
