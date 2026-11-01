<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Platform\MySql\Lowering\Utility\SyntaxInspection;

#[CoversClass(SyntaxInspection::class)]
#[Medium]
final class SyntaxInspectionTest extends TestCase
{
    public function testCompletedRetainsOnlyOffsetsBeforeAnUnrelatedTrailingToken(): void
    {
        $parser = new MySqlParser('mysql-9.1.0');
        $input = 'SHOW PARSE_TREE DROP RESOURCE GROUP g FOR ?';
        self::assertSame([5, 37], (new SyntaxInspection())->completed($parser, $input, strlen($input)));
        self::assertNull((new SyntaxInspection())->completed($parser, $input, 36));
    }

    public function testCompletedLeavesAnUnfinishedInspectedStatementToTheParser(): void
    {
        $parser = new MySqlParser('mysql-9.1.0');
        self::assertNull((new SyntaxInspection())->completed($parser, 'SHOW PARSE_TREE SET RESOURCE GROUP g FOR 1 broken', 100));
        self::assertNull((new SyntaxInspection())->completed($parser, 'SHOW PARSE_TREE SELECT 1 +', 100));
        self::assertNull((new SyntaxInspection())->completed($parser, 'SELECT parse_tree', 100));
        self::assertNull((new SyntaxInspection())->completed($parser, 'SELECT 1', 100));
    }
}
