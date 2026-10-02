<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Input;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\Input as I;
use SqlSemantics\Statement\Construction as C;

#[CoversClass(I\ConversionInputReader::class)]
#[Small]
final class ConversionInputReaderTest extends TestCase
{
    public function testReadPreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT CAST(7 AS TEXT)')->find('expr')[0];
        $input = (new I\ConversionInputReader())->read($source, new I\ExpressionInputReader());
        self::assertNotNull($input);
        self::assertSame('CAST(7 AS "TEXT")', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testCastPreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT CAST(7 AS TEXT)')->find('expr')[0];
        $input = (new I\ConversionInputReader())->cast($source, new I\ExpressionInputReader());
        self::assertSame('CAST(7 AS "TEXT")', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testCollatedPreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT 7 COLLATE nocase')->find('expr')[0];
        $input = (new I\ConversionInputReader())->collated($source, new I\ExpressionInputReader());
        self::assertSame('(7) COLLATE nocase', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testTargetUsesTheCompleteDecodedTypeName(): void
    {
        $source = (new SqliteParser())->parse('SELECT CAST(7 AS FLOATING POINT)')->find('typetoken')[0];
        self::assertSame('"FLOATING POINT"', (new I\ConversionInputReader())->target($source)->toString());
    }
}
