<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\CodeBlockReader;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\SourceReader;

#[CoversClass(CodeBlockReader::class)]
#[UsesClass(GrammarSourceException::class)]
#[UsesClass(SourceReader::class)]
#[Small]
final class CodeBlockReaderTest extends TestCase
{
    public function testReadIgnoresBracesInLiteralsAndComments(): void
    {
        $source = "{ if (c == '{') { s = \"}\"; } /* } */ // }\n } rest";
        $reader = new SourceReader($source);

        self::assertSame("{ if (c == '{') { s = \"}\"; }         // }\n }", (new CodeBlockReader())->read($reader));
        self::assertSame(' rest', $reader->take(5));
    }

    public function testReadRejectsAnUnterminatedBlock(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new CodeBlockReader())->read(new SourceReader('{ open'));
    }

    public function testReadUnit(): void
    {
        $reader = new CodeBlockReader();

        self::assertSame('"a}b"', $reader->readUnit(new SourceReader('"a}b" x')));
        self::assertSame("'}'", $reader->readUnit(new SourceReader("'}' x")));
        self::assertSame('    ', $reader->readUnit(new SourceReader('/**/ x')));
        self::assertSame('// x', $reader->readUnit(new SourceReader("// x\ny")));
        self::assertSame('z', $reader->readUnit(new SourceReader('zz')));
        self::assertSame('"', $reader->readUnit(new SourceReader('"open')));
    }
}
