<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\PostgreSql\PgProfileBuilder;

#[CoversClass(PgProfileBuilder::class)]
final class PgProfileBuilderTest extends TestCase
{
    public function testSourceUrlsReadsTheKeywordListTheScannerAndTheParser(): void
    {
        $urls = (new PgProfileBuilder())->sourceUrls('pg-17.2');

        self::assertStringEndsWith('/src/include/parser/kwlist.h', $urls['keywords']);
        self::assertStringEndsWith('/src/backend/parser/scan.l', $urls['scanner']);
        self::assertStringEndsWith('/src/backend/parser/parser.c', $urls['parser']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSourceFile(): array
    {
        return ['keywords' => ['keywords'], 'scanner' => ['scanner'], 'parser' => ['parser']];
    }

    #[DataProvider('providerSourceFile')]
    public function testSourceUrlsSpellsTheVersionBackIntoAReleaseTag(string $file): void
    {
        self::assertStringContainsString('/refs/tags/REL_17_2', (new PgProfileBuilder())->sourceUrls('pg-17.2')[$file]);
    }

    public function testBuildReportsAnUpstreamFileItCannotRead(): void
    {
        $source = self::createStub(LexerSource::class);
        $source->method('fetch')->willThrowException(new RuntimeException('Failed to fetch'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch');

        (new PgProfileBuilder($source))->build('pg-17.2');
    }

    public function testCatalogReportsAScannerThatDeclaresNoRules(): void
    {
        $this->expectException(RuntimeException::class);

        (new PgProfileBuilder())->catalog(
            ['keywords' => [], 'lookahead' => []],
            ['states' => [], 'rules' => [], 'lookahead_tokens' => []],
        );
    }

    public function testWitnessNamesTheSqlThatProvesATerminalCanBeLexed(): void
    {
        self::assertSame(
            ['id' => 'ident.bare', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => []],
            (new PgProfileBuilder())->witness('ident.bare', 'users', ['IDENT']),
        );
    }

    public function testRuleWitnessesAnswersTheWitnessAScannerRuleIsCoveredBy(): void
    {
        $witnesses = (new PgProfileBuilder())->ruleWitnesses();

        self::assertSame('postgresql.lookahead.FORMAT_LA', $witnesses[1]);
        self::assertSame('postgresql.family.BCONST.0', $witnesses[8]);
    }

    public function testAttachUnitRecordsTheUnitAgainstTheWitnessThatNamesIt(): void
    {
        $terminals = ['IDENT' => [['id' => 'ident.bare', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => []]]];

        (new PgProfileBuilder())->attachUnit($terminals, 'ident.bare', 'identifier');

        self::assertSame(['identifier'], $terminals['IDENT'][0]['units']);
    }

    public function testAttachUnitReportsAWitnessNoTerminalCarries(): void
    {
        $terminals = [];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unknown witness: ident.bare');

        (new PgProfileBuilder())->attachUnit($terminals, 'ident.bare', 'identifier');
    }
}
