<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Input;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\Input as I;
use SqlSemantics\Statement\Construction as C;

#[CoversClass(I\ExpressionInputReader::class)]
#[Small]
final class ExpressionInputReaderTest extends TestCase
{
    public function testReadPreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT 1+2')->find('expr')[0];
        $input = (new I\ExpressionInputReader())->read($source);
        self::assertSame('1+2', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testReferencePreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT x.id')->find('expr')[0];
        $input = (new I\ExpressionInputReader())->reference($source);
        self::assertSame('x.id', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testInfixPreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT 1-2')->find('expr')[0];
        $input = (new I\ExpressionInputReader())->infix($source);
        self::assertNotNull($input);
        self::assertSame('1-2', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testPredicatePreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT 1 NOT IN (2, 3)')->find('expr')[0];
        $input = (new I\ExpressionInputReader())->predicate($source);
        self::assertNotNull($input);
        self::assertSame('(1) NOT IN (2, 3)', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testColumnPreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT x.id')->find('expr')[0];
        $input = (new I\ExpressionInputReader())->column($source);
        self::assertSame('x.id', (new C\Rendering\ExpressionSql())->write($input));
    }
}
