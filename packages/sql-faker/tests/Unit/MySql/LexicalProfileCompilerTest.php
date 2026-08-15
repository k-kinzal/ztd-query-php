<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\MySql\LexicalProfileCompiler;

#[CoversClass(LexicalProfileCompiler::class)]
final class LexicalProfileCompilerTest extends TestCase
{
    public function testCompilesModernKeywordKindsAndFunctionTokens(): void
    {
        $source = <<<'SOURCE'
static const SYMBOL symbols[] = {
  {SYM_HK("UPDATE", UPDATE_SYM)},
  {SYM("SELECT", SELECT_SYM)},
  {SYM("SELECT", SELECT_SYM)},
  {SYM_H("BKA", BKA_HINT)},
  {SYM_FN("COUNT", COUNT_SYM)},
  {SYM_FN("JSON\"VALUE", JSON_VALUE_SYM)}
};
SOURCE;

        self::assertSame([
            'symbols' => [
                'BKA_HINT' => ['BKA'],
                'SELECT_SYM' => ['SELECT'],
                'UPDATE_SYM' => ['UPDATE'],
            ],
            'functions' => [
                'COUNT_SYM' => ['COUNT'],
                'JSON_VALUE_SYM' => ['JSON"VALUE'],
            ],
        ], (new LexicalProfileCompiler())->compile($source));
    }

    public function testCompilesLegacySeparatedTables(): void
    {
        $source = <<<'SOURCE'
{ "SELECT", SYM(SELECT_SYM)}
sql_functions
{
  { "COUNT", SYM(COUNT_SYM)}
}
SOURCE;

        self::assertSame([
            'symbols' => ['SELECT_SYM' => ['SELECT']],
            'functions' => ['COUNT_SYM' => ['COUNT']],
        ], (new LexicalProfileCompiler())->compile($source));
    }

    public function testRejectsMissingTables(): void
    {
        $this->expectException(RuntimeException::class);

        (new LexicalProfileCompiler())->compile('');
    }

    public function testRejectsLegacySourceWithOnlyFunctionTable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL lexical tables were empty.');

        (new LexicalProfileCompiler())->compile(<<<'SOURCE'
static SYMBOL symbols[] = {};
static SYMBOL sql_functions[] = {
  { "COUNT", SYM(COUNT_SYM)}
};
SOURCE);
    }
}
