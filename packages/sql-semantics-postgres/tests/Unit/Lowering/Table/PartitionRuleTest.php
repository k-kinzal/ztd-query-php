<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\PartitionRule::class)]
#[Medium]
final class PartitionRuleTest extends TestCase
{
    public function testSpecificationIsNullWhenNotWritten(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\PartitionRule($lowering))->specification($tree->find('OptPartitionSpec')[0]);
        self::assertSame(null, $value);
    }

    public function testElementLowersAnExpressionKey(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int) PARTITION BY RANGE ((a + 1))');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\PartitionRule($lowering))->element($tree->find('part_elem')[0]);
        $n1 = $value->key;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ExpressionKey::class, $n1);
        self::assertSame(true, $n1->parenthesized);
    }

    public function testBoundLowersAHashBound(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE p1 PARTITION OF p FOR VALUES WITH (MODULUS 2, REMAINDER 0)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\PartitionRule($lowering))->bound($tree->find('PartitionBoundSpec')[0]);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Partition\\HashBound', get_debug_type($value));
    }

    public function testHashLowersTheItems(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE p1 PARTITION OF p FOR VALUES WITH (MODULUS 2, REMAINDER 0)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\PartitionRule($lowering))->hash($tree->find('hash_partbound')[0]);
        self::assertSame(2, count($value));
    }

    public function testDatumsTurnsMinvalueIntoAnInfiniteBound(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE p1 PARTITION OF p FOR VALUES FROM (MINVALUE, 1) TO (2, 3)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\PartitionRule($lowering))->datums($tree->find('expr_list')[0]);
        self::assertSame([
          0 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Partition\\RangeLimit',
          1 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Literal\\Constant',
        ], array_map(static fn ($datum): string => $datum::class, $value));
    }
}
