<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use MySqlMemory\Server\InitialHelp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(InitialHelp::class)]
#[Small]
final class InitialHelpTest extends TestCase
{
    public function testWritePreservesLargeTextAndControlCharacters(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $tables = ['topics' => [['1', 'Text', '1', str_repeat("a\0é\n", 30000), '']]];
        (new InitialHelp())->write($stream, $tables);
        rewind($stream);

        self::assertSame($tables, (new InitialHelp())->read($stream));
        fclose($stream);
    }

    public function testReadKeepsEmptyTables(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, '{"topics":[],"categories":[],"keywords":[],"relations":[]}');
        rewind($stream);

        self::assertSame(['topics' => [], 'categories' => [], 'keywords' => [], 'relations' => []], (new InitialHelp())->read($stream));
        fclose($stream);
    }

}
