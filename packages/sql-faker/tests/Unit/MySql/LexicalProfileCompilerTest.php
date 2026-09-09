<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\MySql\LexicalProfileCompiler;

#[CoversClass(LexicalProfileCompiler::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RegistrationTable::class)]
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
static SYMBOL symbols[] = {{ "SELECT", SYM(SELECT_SYM)}};
static SYMBOL sql_functions[] = {
  { "COUNT", SYM(COUNT_SYM)}
};
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

    public function testExtractModernReadsTheTableReleasesFromEightOnwardDeclare(): void
    {
        self::assertSame(
            ['SELECT_SYM' => ['SELECT']],
            (new LexicalProfileCompiler())->extractModern('{ SYM("SELECT", SELECT_SYM) }', 'SYM'),
        );
    }

    public function testExtractLegacyReadsTheTableEarlierReleasesDeclare(): void
    {
        self::assertSame(
            ['SELECT_SYM' => ['SELECT']],
            (new LexicalProfileCompiler())->extractLegacy('{ "SELECT", SYM(SELECT_SYM) }'),
        );
    }

    public function testGroupFilesEachLexemeUnderItsTokenWithoutRepeatingOne(): void
    {
        self::assertSame(
            ['SELECT_SYM' => ['SELECT']],
            (new LexicalProfileCompiler())->group([['', 'SELECT', 'SELECT_SYM'], ['', 'SELECT', 'SELECT_SYM']]),
        );
    }

    public function testRegistrationsKeepsHintOnlyAndFunctionClassesDistinct(): void
    {
        $source = '{SYM("SELECT", SELECT_SYM)}, {SYM_FN("NOW", NOW_SYM)}, {SYM_HK("DELETE", DELETE_SYM)}, {SYM_H("BKA", BKA_HINT)}';
        $result = (new LexicalProfileCompiler())->registrations($source);
        self::assertSame(['SELECT_SYM' => ['SELECT']], $result['SYM']);
        self::assertSame(['NOW_SYM' => ['NOW']], $result['SYM_FN']);
        self::assertSame(['DELETE_SYM' => ['DELETE']], $result['SYM_HK']);
        self::assertSame(['BKA_HINT' => ['BKA']], $result['SYM_H']);
    }

    public function testUnsupportedEntryCannotBeHiddenByAnotherSpellingOfTheSameToken(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported registration declaration');
        (new LexicalProfileCompiler())->registrations('static const SYMBOL symbols[] = {{SYM("SELECT", SELECT_SYM)}, {SYM_FN("NOW", NOW_SYM)}, {SYM_FN("CURRENT_" "TIMESTAMP", NOW_SYM)}};');
    }
}
