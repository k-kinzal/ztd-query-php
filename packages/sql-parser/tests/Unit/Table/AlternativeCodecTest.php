<?php

declare(strict_types=1);

namespace Tests\Unit\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Table\ActionCode;
use SqlParser\Table\ArrayRows;
use SqlParser\Table\PackedRows;
use SqlParser\Table\ParseTable;
use SqlParser\Table\TableCodec;
use SqlParser\Table\TableRule;

#[CoversClass(TableCodec::class)]
#[CoversClass(ActionCode::class)]
#[CoversClass(ArrayRows::class)]
#[CoversClass(PackedRows::class)]
#[CoversClass(ParseTable::class)]
#[CoversClass(SymbolTable::class)]
#[CoversClass(TableRule::class)]
#[Small]
#[CoversClass(\SqlParser\Parser\AlternativeParser::class)]
#[CoversClass(\SqlParser\Parser\ParseBranch::class)]
#[CoversClass(\SqlParser\Table\AlternativeCodec::class)]
final class AlternativeCodecTest extends TestCase
{
    public function testEncodeRetainsSignedActionsAndEveryState(): void
    {
        $codec = new \SqlParser\Table\AlternativeCodec();
        self::assertSame(pack('l*', 4, 7, 2, -8, -9, 5, 2, 1, -3), $codec->encode([4 => [7 => [-8, -9]], 5 => [2 => [-3]]]));
        self::assertSame('', $codec->encode([]));
    }

    public function testDecodeReadsLegacyTablesAndConflictExtensions(): void
    {
        $codec = new \SqlParser\Table\AlternativeCodec();
        self::assertSame([], $codec->decode(''));
        self::assertSame([4 => [7 => [-8, -9]], 5 => [2 => [-3]]], $codec->decode(pack('l*', 4, 7, 2, -8, -9, 5, 2, 1, -3)));
        $table = new ParseTable(new SymbolTable(['$end', 'A'], ['$accept', 's']), [new TableRule(2, 2, 0)], [0], new ArrayRows([[1 => 3]]), alternatives: [0 => [1 => [-1, -2]]]);
        $decoded = (new TableCodec())->decode((new TableCodec())->encode($table));
        self::assertSame($table->alternatives, $decoded->alternatives);
        self::assertSame([1 => 3], $decoded->rows->row(0));
    }
}
