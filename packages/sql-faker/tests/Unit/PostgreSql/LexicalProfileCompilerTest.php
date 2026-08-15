<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\PostgreSql\LexicalProfileCompiler;

#[CoversClass(LexicalProfileCompiler::class)]
final class LexicalProfileCompilerTest extends TestCase
{
    public function testCompilesKeywordTokens(): void
    {
        $source = <<<'SOURCE'
PG_KEYWORD("current_date", CURRENT_DATE, RESERVED_KEYWORD, BARE_LABEL)
PG_KEYWORD("analyze", ANALYZE, RESERVED_KEYWORD, BARE_LABEL)
PG_KEYWORD("analyse", ANALYSE, RESERVED_KEYWORD, BARE_LABEL)
PG_KEYWORD("also_analyse", ANALYSE, RESERVED_KEYWORD, BARE_LABEL)
SOURCE;

        self::assertSame([
            'ANALYSE' => ['ANALYSE', 'ALSO_ANALYSE'],
            'ANALYZE' => ['ANALYZE'],
            'CURRENT_DATE' => ['CURRENT_DATE'],
        ], (new LexicalProfileCompiler())->compile($source));
    }

    public function testRejectsMissingKeywordTable(): void
    {
        $this->expectException(RuntimeException::class);

        (new LexicalProfileCompiler())->compile('');
    }
}
