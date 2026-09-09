<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\Grammar\Lexical\TerminalInventory;
use SqlFaker\Grammar\Resource\SqlVersionRegistry;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\MySql\Grammar\MySqlGrammar;
use SqlFaker\MySql\MySqlProfileBuilder;

#[CoversClass(MySqlProfileBuilder::class)]
#[UsesClass(\SqlFaker\MySql\LexicalProfileCompiler::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\UpstreamLexerSource::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(SqlVersionRegistry::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(TerminalInventory::class)]
#[UsesClass(MySqlGrammar::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(\SqlFaker\Grammar\Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RegistrationTable::class)]
final class MySqlProfileBuilderTest extends TestCase
{
    public function testSourceUrlsReadsTheKeywordTableAndTheScanner(): void
    {
        $urls = (new MySqlProfileBuilder())->sourceUrls('mysql-8.4.7');

        self::assertStringEndsWith('/sql/lex.h', $urls['table']);
        self::assertStringEndsWith('/sql/sql_lex.cc', $urls['scanner']);
    }

    public function testSourceUrlsFollowsTheStateHeaderWhereEachReleaseKeepsIt(): void
    {
        $builder = new MySqlProfileBuilder();

        self::assertStringEndsWith('/include/m_ctype.h', $builder->sourceUrls('mysql-5.6.51')['state']);
        self::assertStringEndsWith('/include/sql_chars.h', $builder->sourceUrls('mysql-8.0.44')['state']);
        self::assertStringEndsWith('/strings/sql_chars.h', $builder->sourceUrls('mysql-8.4.7')['state']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSourceFile(): array
    {
        return ['table' => ['table'], 'scanner' => ['scanner'], 'state' => ['state']];
    }

    #[DataProvider('providerSourceFile')]
    public function testSourceUrlsPointsEveryFileAtTheReleaseTag(string $file): void
    {
        self::assertStringContainsString(
            '/refs/tags/mysql-8.4.7',
            (new MySqlProfileBuilder())->sourceUrls('mysql-8.4.7')[$file],
        );
    }

    public function testBuildReportsAnUpstreamFileItCannotRead(): void
    {
        $source = self::createStub(LexerSource::class);
        $source->method('fetch')->willThrowException(new RuntimeException('Failed to fetch'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch');

        (new MySqlProfileBuilder($source))->build('mysql-8.4.7');
    }





























    public function testBuildRetainsSourceHashesAndOnlyRegistrationFacts(): void
    {
        $builder = new MySqlProfileBuilder();
        $urls = $builder->sourceUrls('mysql-8.4.7');
        $table = '{ SYM("SELECT", SELECT_SYM) }, { SYM_FN("NOW", NOW_SYM) }, { SYM_HK("BKA", BKA_HINT) }, { SYM_H("HINT", HINT_ONLY) }';
        $source = $this->createMock(LexerSource::class);
        $source->method('fetch')->willReturnCallback(static fn (string $url): string => $url === $urls['table'] ? $table : 'reviewed scanner source');
        $profile = (new MySqlProfileBuilder($source))->build('mysql-8.4.7');
        self::assertSame('mysql', $profile['dialect']);
        self::assertSame('mysql-8.4.7', $profile['version']);
        self::assertIsArray($profile['sources']);
        self::assertSame(hash('sha256', $table), $profile['sources'][$urls['table']]);
        self::assertArrayNotHasKey('catalog', $profile);
        self::assertArrayNotHasKey('lookahead', $profile);
    }
}
