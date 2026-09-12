<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Routine\JsonTablePathRule;

#[CoversClass(JsonTablePathRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class JsonTablePathRuleTest extends TestCase
{
    #[DataProvider('providerPaths')]
    public function testRewriteOnlyTheDirectPathIsReplaced(string $name, string $expected): void
    {
        $context = new TerminalOccurrence('IDENT', 10, [0, 1, 2], ['json_table', 'json_value_expr', 'a_expr']);
        $path = new TerminalOccurrence($name, 11, [0, 3], ['json_table', 'a_expr']);
        $column = new TerminalOccurrence('IDENT', 12, [0, 4], ['json_table', 'json_table_column_definition']);
        $input = new TerminalSequence([$context, $path, $column], [$context, $path, $column], [], [
            new ProductionOccurrence(0, null, 'json_table', 0), new ProductionOccurrence(1, 0, 'json_value_expr', 0),
            new ProductionOccurrence(2, 1, 'a_expr', 0), new ProductionOccurrence(3, 0, 'a_expr', 0),
            new ProductionOccurrence(4, 0, 'json_table_column_definition', 0),
        ]);
        $rule = new JsonTablePathRule();
        $result = $rule->rewrite($input);
        self::assertSame(['IDENT', $expected, 'IDENT'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($context, $result->terminals[0]);
        self::assertSame($column, $result->terminals[2]);
        self::assertSame($result, $rule->rewrite($result));
        self::assertSame(TerminalSequence::class, $rule->rewrite(TerminalSequence::fromNames([]))::class);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function providerPaths(): iterable
    {
        yield ['IDENT', 'JSON_TABLE_PATH'];
        yield ['ICONST', 'JSON_TABLE_PATH'];
        yield ['SCONST', 'SCONST'];
        yield ['JSON_TABLE_PATH', 'JSON_TABLE_PATH'];
    }
}
