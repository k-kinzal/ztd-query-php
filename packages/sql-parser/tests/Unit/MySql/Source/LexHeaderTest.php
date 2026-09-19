<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\MySql\Source\LexHeader;

#[CoversClass(LexHeader::class)]
#[Small]
final class LexHeaderTest extends TestCase
{
    public function testParseReadsTheModernSpelling(): void
    {
        $source = "static const SYMBOL symbols[] = {\n    {SYM(\"&&\", AND_AND_SYM)},\n    {SYM_FN(\"ADDDATE\", ADDDATE_SYM)},\n    {SYM_HK(\"SELECT\", SELECT_SYM)},\n    {SYM_H(\"BKA\", BKA_HINT)},\n    {SYM(\"SOURCE_CONNECTION_AUTO_FAILOVER\",\n         SOURCE_CONNECTION_AUTO_FAILOVER_SYM)},\n};";
        $tables = (new LexHeader())->parse($source);

        self::assertSame(['&&' => 'AND_AND_SYM', 'SELECT' => 'SELECT_SYM', 'SOURCE_CONNECTION_AUTO_FAILOVER' => 'SOURCE_CONNECTION_AUTO_FAILOVER_SYM'], $tables['keywords']);
        self::assertSame(['ADDDATE' => 'ADDDATE_SYM'], $tables['functions']);
    }

    public function testParseReadsTheLegacySpelling(): void
    {
        $source = "static SYMBOL symbols[] = {\n  { \"select\",\t\tSYM(SELECT_SYM)},\n};\nstatic SYMBOL sql_functions[] = {\n  { \"COUNT\",\t\tSYM(COUNT_SYM)},\n};";
        $tables = (new LexHeader())->parse($source);

        self::assertSame(['SELECT' => 'SELECT_SYM'], $tables['keywords']);
        self::assertSame(['COUNT' => 'COUNT_SYM'], $tables['functions']);
    }

    public function testParseRejectsAFileWithoutKeywords(): void
    {
        $this->expectException(RuntimeException::class);

        (new LexHeader())->parse('int x;');
    }

    public function testEntries(): void
    {
        $entries = (new LexHeader())->entries('{SYM_FN("x", X_SYM)} { "y", SYM(Y_SYM)}');

        self::assertSame([['SYM_FN', 'X', 'X_SYM', 0], ['SYM', 'Y', 'Y_SYM', 21]], $entries);
    }
}
