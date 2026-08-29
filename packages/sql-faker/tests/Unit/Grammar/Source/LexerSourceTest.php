<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Source\LexerSource;

#[CoversNothing]
final class LexerSourceTest extends TestCase
{
    public function testFetchReturnsWhatTheImplementationRead(): void
    {
        $source = self::createStub(LexerSource::class);
        $source->method('fetch')->willReturn('lexer source');

        self::assertSame('lexer source', $source->fetch('https://example.com/lex.h'));
    }

    public function testFetchReportsAFileThatCannotBeRead(): void
    {
        $source = self::createStub(LexerSource::class);
        $source->method('fetch')->willThrowException(new RuntimeException('Failed to fetch'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch');

        $source->fetch('https://example.com/missing.h');
    }
}
