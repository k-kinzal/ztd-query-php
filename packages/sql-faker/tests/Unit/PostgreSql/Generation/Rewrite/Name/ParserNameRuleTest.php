<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Name\ParserNameRule;

#[CoversClass(ParserNameRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ParserNameRuleTest extends TestCase
{
    #[DataProvider('providerNames')]
    public function testRewriteRestrictsOnlyTheDirectParserNameChild(string $owner, string $child, string $expected): void
    {
        $terminals = [new TerminalOccurrence('IDENT', 10, [0, 1], [$owner, $child]), new TerminalOccurrence('IDENT', 11, [0, 2, 3], [$owner, 'nested', $child])];
        $sequence = new TerminalSequence($terminals, $terminals, [], [new ProductionOccurrence(0, null, $owner, 0), new ProductionOccurrence(1, 0, $child, 0), new ProductionOccurrence(2, 0, 'nested', 0), new ProductionOccurrence(3, 2, $child, 0)]);
        $rule = new ParserNameRule();
        $result = $rule->rewrite($sequence);
        self::assertSame([$expected, 'IDENT'], $result->names());
        self::assertSame($sequence->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return list<array{string, string, string}>
     */
    public static function providerNames(): array
    {
        return [['PartitionSpec', 'ColId', 'PARTITION_STRATEGY'], ['json_format_clause', 'name', 'JSON_ENCODING']];
    }

    #[DataProvider('providerRoles')]
    public function testRewriteAllowsSpecialRoleReferencesOutsideLiteralRoleId(string $name): void
    {
        $terminals = [new TerminalOccurrence($name, 10, [0, 1], ['RoleId', 'RoleSpec']), new TerminalOccurrence($name, 11, [2], ['RoleSpec'])];
        $rule = new ParserNameRule();
        $result = $rule->rewrite(new TerminalSequence($terminals, $terminals));
        self::assertSame(['IDENT', $name], $result->names());
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return list<array{string}>
     */
    public static function providerRoles(): array
    {
        return [['CURRENT_ROLE'], ['CURRENT_USER'], ['SESSION_USER']];
    }

    public function testRewriteRetainsMissingAndEmptyNames(): void
    {
        $sequence = new TerminalSequence([], [], [], [new ProductionOccurrence(0, null, 'PartitionSpec', 0), new ProductionOccurrence(1, 0, 'ColId', 0), new ProductionOccurrence(2, null, 'json_format_clause', 0)]);
        self::assertSame($sequence, (new ParserNameRule())->rewrite($sequence));
    }
}
