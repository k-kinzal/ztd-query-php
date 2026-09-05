<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Lexical\UpstreamLexerSource;

#[CoversClass(UpstreamLexerSource::class)]
final class UpstreamLexerSourceTest extends TestCase
{
    public function testFetchReadsALocalFileThroughItsStreamWrapper(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'lexer-source-');
        self::assertIsString($path);
        file_put_contents($path, 'static const SYMBOL symbols[] = {};');

        try {
            self::assertSame('static const SYMBOL symbols[] = {};', (new UpstreamLexerSource())->fetch($path));
        } finally {
            unlink($path);
        }
    }

    public function testFetchReportsAFileThatCannotBeRead(): void
    {
        $this->expectException(RuntimeException::class);

        (new UpstreamLexerSource())->fetch(sys_get_temp_dir() . '/no-such-lexer-source');
    }
}
