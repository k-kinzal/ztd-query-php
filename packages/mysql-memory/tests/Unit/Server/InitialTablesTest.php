<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use MySqlMemory\Server\InitialTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(InitialTables::class)]
#[Small]
final class InitialTablesTest extends TestCase
{
    public function testWritePreservesLargeTextAndControlCharacters(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $tables = ['topics' => [['1', 'Text', '1', str_repeat("a\0é\n", 30000), '']]];
        (new InitialTables())->write($stream, $tables);
        rewind($stream);

        self::assertSame($tables, (new InitialTables())->read($stream));
        fclose($stream);
    }

    public function testReadKeepsEmptyTables(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, '{"topics":[],"categories":[],"keywords":[],"relations":[]}');
        rewind($stream);

        self::assertSame(['topics' => [], 'categories' => [], 'keywords' => [], 'relations' => []], (new InitialTables())->read($stream));
        fclose($stream);
    }

}
