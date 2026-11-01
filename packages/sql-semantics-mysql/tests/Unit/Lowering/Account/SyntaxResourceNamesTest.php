<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Platform\MySql\Lowering\Account\SyntaxResourceNames;

#[CoversClass(SyntaxResourceNames::class)]
#[Medium]
final class SyntaxResourceNamesTest extends TestCase
{
    public function testCompletedRetainsANameBeforeAnIncompleteCommand(): void
    {
        $sql = 'CREATE RESOURCE GROUP `😀` nonsense';
        $notices = (new SyntaxResourceNames())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql));

        self::assertCount(1, $notices);
        self::assertSame('😀', $notices[0][1]->name->value);
        self::assertSame(strlen('CREATE RESOURCE GROUP `😀`'), $notices[0][0]);
    }

    public function testCompletedIgnoresQuotedKeywordsAndAnEarlierBoundary(): void
    {
        $parser = new MySqlParser('mysql-8.4.7');

        self::assertSame([], (new SyntaxResourceNames())->completed($parser, 'CREATE `RESOURCE` GROUP `😀`', 100));
        self::assertSame([], (new SyntaxResourceNames())->completed($parser, 'CREATE RESOURCE GROUP `😀`', 6));
    }
}
