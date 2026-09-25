<?php

declare(strict_types=1);

namespace Tests\Unit\Php;

use PhpParser\Node\Stmt\Nop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Php\ParsedFile;

#[CoversClass(ParsedFile::class)]
final class ParsedFileTest extends TestCase
{
    public function testKeepsThePathAndTheStatements(): void
    {
        $statement = new Nop();
        $file = new ParsedFile('src/a.php', [$statement]);
        self::assertSame('src/a.php', $file->path);
        self::assertSame([$statement], $file->statements);
    }
}
