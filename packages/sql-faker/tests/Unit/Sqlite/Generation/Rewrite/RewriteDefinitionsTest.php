<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Sqlite\Generation\Rewrite\RewriteDefinitions;

#[CoversClass(RewriteDefinitions::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenRewriter::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\IdentifierListRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\JoinRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\StrictTableRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\TableOptionRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\WindowFrameRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\WithoutRowidRule::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ExpressionGroupingRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\CompoundSelectRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\FunctionArgumentRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalMappingRule::class)]
final class RewriteDefinitionsTest extends TestCase
{
    public function testCreateMapsOnlyTheGeneratedColumnStorageName(): void
    {
        $expression = new \SqlFaker\Grammar\Generation\Token\TerminalOccurrence('ID', 2, [0, 1], ['generated', 'expr']);
        $storage = new \SqlFaker\Grammar\Generation\Token\TerminalOccurrence('ID', 3, [0], ['generated']);
        $plain = new \SqlFaker\Grammar\Generation\Token\TerminalOccurrence('ID', 4, [5], ['nm']);
        $input = new \SqlFaker\Grammar\Generation\Token\TerminalSequence([$expression, $storage, $plain], [$expression, $storage, $plain]);
        $result = (new RewriteDefinitions())->create()->rewrite($input);
        self::assertSame(['ID', 'GENERATED_STORAGE', 'ID'], $result->names());
        self::assertSame($expression, $result->terminals[0]);
        self::assertSame($plain, $result->terminals[2]);
        self::assertSame($storage->id, $result->terminals[1]->id);
    }
    public function testCreateComposesTheDeclaredSourceRules(): void
    {
        $trace = new DerivationTrace('table_option');
        $trace->expand(0, new Production([new Terminal('ID')]), 0);
        $result = (new RewriteDefinitions())->create()->rewrite($trace->terminals());
        self::assertSame(['STRICT_TABLE_OPTION'], $result->names());
    }
}
