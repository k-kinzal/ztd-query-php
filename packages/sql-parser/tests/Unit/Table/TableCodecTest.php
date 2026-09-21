<?php

declare(strict_types=1);

namespace Tests\Unit\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Table\ActionCode;
use SqlParser\Table\ArrayRows;
use SqlParser\Table\PackedRows;
use SqlParser\Table\ParseTable;
use SqlParser\Table\TableCodec;
use SqlParser\Table\TableRule;

#[CoversClass(TableCodec::class)]
#[UsesClass(ActionCode::class)]
#[UsesClass(ArrayRows::class)]
#[UsesClass(PackedRows::class)]
#[UsesClass(ParseTable::class)]
#[UsesClass(SymbolTable::class)]
#[UsesClass(TableRule::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class TableCodecTest extends TestCase
{
    public function testEncodeAndDecodeRoundTrip(): void
    {
        $symbols = new SymbolTable(['$end', 'ID', 'ABORT', 'ANY'], ['$accept', 's']);
        $table = new ParseTable($symbols, [new TableRule(4, 2, 0), new TableRule(5, 1, 3, true)], [ActionCode::reduce(0), ActionCode::ERROR], new ArrayRows([[1 => 2, 5 => -3], []]), [2 => 1], 3);
        $codec = new TableCodec();
        $decoded = $codec->decode($codec->encode($table));

        self::assertSame(['$end', 'ID', 'ABORT', 'ANY'], $decoded->symbols->terminals());
        self::assertSame(['$accept', 's'], $decoded->symbols->nonterminals());
        self::assertSame(2, $decoded->rules[0]->length);
        self::assertTrue($decoded->rules[1]->hidden);
        self::assertSame(3, $decoded->rules[1]->ordinal);
        self::assertSame([ActionCode::reduce(0), ActionCode::ERROR], $decoded->defaults);
        self::assertSame([1 => 2, 5 => -3], $decoded->rows->row(0));
        self::assertSame([], $decoded->rows->row(1));
        self::assertSame([2 => 1], $decoded->fallbacks);
        self::assertSame(3, $decoded->wildcard);
    }

    public function testDecodeKeepsAMissingWildcard(): void
    {
        $codec = new TableCodec();
        $table = new ParseTable(new SymbolTable(['$end'], ['$accept']), [], [], new ArrayRows([]));

        self::assertNull($codec->decode($codec->encode($table))->wildcard);
        self::assertSame(0, $codec->decode($codec->encode($table))->stateCount());
    }

    public function testDecodeRejectsForeignBytes(): void
    {
        $this->expectException(RuntimeException::class);

        (new TableCodec())->decode('not a table');
    }

    public function testEncodeRowSortsBySymbol(): void
    {
        $codec = new TableCodec();

        self::assertSame(pack('vv', 1, 5) . pack('ll', 9, 8), $codec->encodeRow([5 => 8, 1 => 9]));
    }

    public function testIntegers(): void
    {
        self::assertSame([1, 65535], TableCodec::integers('v*', pack('vv', 1, 65535)));
        self::assertSame([], TableCodec::integers('v*', ''));
    }

    public function testIntegersRejectsPartialValues(): void
    {
        $this->expectException(RuntimeException::class);

        TableCodec::integers('l*', 'abc');
    }
}
