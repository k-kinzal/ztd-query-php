<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Sqlite\LexicalProfileCompiler;

#[CoversClass(LexicalProfileCompiler::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RegistrationTable::class)]
final class LexicalProfileCompilerTest extends TestCase
{
    public function testCompilesKeywordFamilies(): void
    {
        $source = <<<'SOURCE'
{ "SELECT", "TK_SELECT", ALWAYS, 10 },
{ "CROSS", "TK_JOIN_KW", ALWAYS, 3 },
{ "LEFT", "TK_JOIN_KW", ALWAYS, 4 },
SOURCE;

        self::assertSame([
            'JOIN_KW' => ['CROSS', 'LEFT'],
            'SELECT' => ['SELECT'],
        ], (new LexicalProfileCompiler())->compile($source));
    }

    public function testRejectsMissingKeywordTable(): void
    {
        $this->expectException(RuntimeException::class);

        (new LexicalProfileCompiler())->compile('');
    }
    public function testRejectsPartialRegistrationReads(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported registration declaration');
        (new LexicalProfileCompiler())->compile('{"SELECT", "TK_SELECT", ALWAYS, 1}, {"LOST" "NAME", "TK_SELECT", ALWAYS, 1}');
    }
}
