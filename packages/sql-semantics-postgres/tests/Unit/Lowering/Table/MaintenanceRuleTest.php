<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\MaintenanceRule::class)]
#[Medium]
final class MaintenanceRuleTest extends TestCase
{
    public function testTruncateLowersRestartIdentity(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('TRUNCATE t RESTART IDENTITY');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\MaintenanceRule($lowering))->truncate($tree->find('TruncateStmt')[0]);
        self::assertSame(true, $value->restartIdentity);
    }

    public function testStatisticsLowersTheKeys(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE STATISTICS s ON a, (b + 1) FROM t');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\MaintenanceRule($lowering))->statistics($tree->find('CreateStatsStmt')[0]);
        self::assertSame(2, count($value->keys));
    }

    public function testAlterStatisticsLowersTheTarget(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER STATISTICS s SET STATISTICS 3');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\MaintenanceRule($lowering))->alterStatistics($tree->find('AlterStatsStmt')[0]);
        $n1 = $value->target?->magnitude;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant::class, $n1);
        self::assertSame('3', $n1->digits);
    }

    public function testAssertionLowersTheCondition(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE ASSERTION a CHECK (true)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\MaintenanceRule($lowering))->assertion($tree->find('CreateAssertionStmt')[0]);
        self::assertSame('a', $value->name->last()->value);
    }
}
