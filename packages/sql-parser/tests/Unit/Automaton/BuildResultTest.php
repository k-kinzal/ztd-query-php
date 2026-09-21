<?php

declare(strict_types=1);

namespace Tests\Unit\Automaton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\BuildResult;
use SqlParser\Automaton\ConflictSummary;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Table\ArrayRows;
use SqlParser\Table\ParseTable;

#[CoversClass(BuildResult::class)]
#[UsesClass(ArrayRows::class)]
#[UsesClass(ConflictSummary::class)]
#[UsesClass(ParseTable::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class BuildResultTest extends TestCase
{
    public function testPropertiesAreKept(): void
    {
        $table = new ParseTable(new SymbolTable(['$end'], ['$accept']), [], [], new ArrayRows([]));
        $conflicts = new ConflictSummary(0, 0, null);
        $result = new BuildResult($table, $conflicts, 7);

        self::assertSame($table, $result->table);
        self::assertSame($conflicts, $result->conflicts);
        self::assertSame(7, $result->stateCount);
    }
}
