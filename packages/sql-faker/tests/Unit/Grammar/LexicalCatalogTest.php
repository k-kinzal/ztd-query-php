<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\TerminalInventory;
use SqlFaker\MySql\Grammar\Grammar as MySqlGrammar;
use SqlFaker\MySql\Grammar\TerminalInventory as MySqlTerminalInventory;
use SqlFaker\PostgreSql\Grammar\PgGrammar;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;

#[CoversClass(LexicalCatalog::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(TerminalInventory::class)]
#[UsesClass(MySqlGrammar::class)]
#[UsesClass(MySqlTerminalInventory::class)]
#[UsesClass(PgGrammar::class)]
#[UsesClass(SqliteGrammar::class)]
#[Medium]
final class LexicalCatalogTest extends TestCase
{
    public function testAcceptsCompletelyClassifiedCatalog(): void
    {
        $catalog = new LexicalCatalog([
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'IDENT' => [[
                    'id' => 'ident.bare',
                    'sql' => 'name',
                    'tokens' => ['IDENT'],
                    'units' => ['identifier'],
                ]],
            ],
            'terminal_exclusions' => ['MODE_ONLY' => 'Not enabled by the selected configuration.'],
            'coverage' => [
                'units' => ['identifier', 'illegal'],
                'witnessed' => ['identifier' => 'ident.bare'],
                'excluded' => ['illegal' => 'The generator emits valid SQL only.'],
            ],
        ]);

        $catalog->assertTerminalsCovered(['IDENT', 'MODE_ONLY']);

        self::assertSame('official', $catalog->sourceEngine());
        self::assertSame('lexer', $catalog->sourceEntrypoint());
        self::assertTrue($catalog->supports('IDENT'));
        self::assertTrue($catalog->excludes('MODE_ONLY'));
        self::assertSame('ident.bare', $catalog->witnesses('IDENT')[0]['id']);
        self::assertSame([], $catalog->witnesses('UNKNOWN'));
        self::assertFalse($catalog->supports('UNKNOWN'));
        self::assertFalse($catalog->excludes('UNKNOWN'));
    }

    public function testAcceptsCompactWitnessWithContext(): void
    {
        $catalog = new LexicalCatalog([
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'FUNCTION' => [[
                    'function.token',
                    'function',
                    ['FUNCTION'],
                    ['function'],
                    'function(',
                ]],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['function'],
                'witnessed' => ['function' => 'function.token'],
                'excluded' => [],
            ],
        ]);

        self::assertSame([
            'id' => 'function.token',
            'sql' => 'function',
            'tokens' => ['FUNCTION'],
            'units' => ['function'],
            'context_sql' => 'function(',
        ], $catalog->witnesses('FUNCTION')[0]);
    }

    public function testRejectsUnclassifiedCoverageUnit(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not completely classified');

        new LexicalCatalog([
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => [],
                'excluded' => [],
            ],
        ]);
    }

    public function testRejectsMissingGrammarTerminal(): void
    {
        $catalog = new LexicalCatalog([
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ]);

        try {
            $catalog->assertTerminalsCovered(['Z_MISSING', 'A_MISSING', 'A_MISSING']);
            self::fail('Missing terminals must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Upstream lexer catalog is missing grammar terminals: A_MISSING, A_MISSING, Z_MISSING',
                $exception->getMessage(),
            );
        }
    }

    public function testRejectsUnknownCoverageWitness(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unknown witness');

        new LexicalCatalog([
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'missing'],
                'excluded' => [],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $catalog
     */
    #[DataProvider('providerInvalidCatalog')]
    public function testRejectsInvalidCatalog(array $catalog, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        new LexicalCatalog($catalog);
    }

    public function testEverySupportedVersionClassifiesItsGrammarTerminals(): void
    {
        $loaders = [
            'mysql' => static fn (string $version): array => MySqlTerminalInventory::fromGrammar(
                MySqlGrammar::load($version),
            ),
            'postgresql' => static fn (string $version): array => TerminalInventory::fromGrammar(
                PgGrammar::load($version),
            ),
            'sqlite' => static fn (string $version): array => TerminalInventory::fromGrammar(
                SqliteGrammar::load($version),
            ),
        ];

        $versions = SqlVersion::all();
        array_walk($versions, static function (SqlVersion $version) use ($loaders): void {
            /** @var array{dialect: string, version: string, catalog: array<string, mixed>} $profile */
            $profile = require $version->lexicalPath;
            $terminals = $loaders[$version->dialect]($version->name);
            $catalog = new LexicalCatalog($profile['catalog']);
            $catalog->assertTerminalsCovered($terminals);
            self::assertSame($version->dialect, $profile['dialect']);
            self::assertSame($version->name, $profile['version']);
            self::assertNotSame([], $terminals);
        });
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function providerInvalidCatalog(): iterable
    {
        $valid = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'IDENT' => [[
                    'id' => 'ident.bare',
                    'sql' => 'name',
                    'tokens' => ['IDENT'],
                    'units' => ['identifier'],
                ]],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'ident.bare'],
                'excluded' => [],
            ],
        ];

        yield 'missing source' => [array_diff_key($valid, ['source' => true]), 'Invalid upstream lexical catalog shape.'];
        yield 'source is not an array' => [[...$valid, 'source' => 'official'], 'Invalid upstream lexical catalog shape.'];
        yield 'source engine is missing' => [[...$valid, 'source' => ['entrypoint' => 'lexer']], 'Invalid upstream lexical catalog shape.'];
        yield 'source engine is not a string' => [[...$valid, 'source' => ['engine' => 1, 'entrypoint' => 'lexer']], 'Invalid upstream lexical catalog shape.'];
        yield 'source entrypoint is not a string' => [[...$valid, 'source' => ['engine' => 'official', 'entrypoint' => 1]], 'Invalid upstream lexical catalog shape.'];
        yield 'terminals is not an array' => [[...$valid, 'terminals' => 'IDENT'], 'Invalid upstream lexical catalog shape.'];
        yield 'terminal exclusions is not an array' => [[...$valid, 'terminal_exclusions' => 'none'], 'Invalid upstream lexical catalog shape.'];
        yield 'coverage is not an array' => [[...$valid, 'coverage' => 'all'], 'Invalid upstream lexical catalog shape.'];
        yield 'coverage units are missing' => [[...$valid, 'coverage' => ['witnessed' => [], 'excluded' => []]], 'Invalid upstream lexical catalog shape.'];
        yield 'coverage units are not an array' => [[...$valid, 'coverage' => ['units' => 'identifier', 'witnessed' => [], 'excluded' => []]], 'Invalid upstream lexical catalog shape.'];
        yield 'coverage witnessed is not an array' => [[...$valid, 'coverage' => ['units' => [], 'witnessed' => 'none', 'excluded' => []]], 'Invalid upstream lexical catalog shape.'];
        yield 'coverage excluded is not an array' => [[...$valid, 'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => 'none']], 'Invalid upstream lexical catalog shape.'];

        yield 'terminal key is not a string' => [[...$valid, 'terminals' => [0 => []]], 'Invalid upstream lexical terminal catalog.'];
        yield 'terminal witnesses is not an array' => [[...$valid, 'terminals' => ['IDENT' => 'name']], 'Invalid upstream lexical terminal catalog.'];
        yield 'witness is not an array' => [[...$valid, 'terminals' => ['IDENT' => ['name']]], 'Invalid terminal witness: IDENT'];
        yield 'compact witness has the wrong length' => [[...$valid, 'terminals' => ['IDENT' => [['id', 'name', ['IDENT']]]]], 'Invalid terminal witness: IDENT'];
        yield 'witness id is not a string' => [[...$valid, 'terminals' => ['IDENT' => [[
            'id' => 1, 'sql' => 'name', 'tokens' => ['IDENT'], 'units' => ['identifier'],
        ]]]], 'Invalid terminal witness: IDENT'];
        yield 'witness sql is not a string' => [[...$valid, 'terminals' => ['IDENT' => [[
            'id' => 'id', 'sql' => 1, 'tokens' => ['IDENT'], 'units' => ['identifier'],
        ]]]], 'Invalid terminal witness: IDENT'];
        yield 'witness tokens is not an array' => [[...$valid, 'terminals' => ['IDENT' => [[
            'id' => 'id', 'sql' => 'name', 'tokens' => 'IDENT', 'units' => ['identifier'],
        ]]]], 'Invalid terminal witness: IDENT'];
        yield 'witness tokens is not a list' => [[...$valid, 'terminals' => ['IDENT' => [[
            'id' => 'id', 'sql' => 'name', 'tokens' => [1 => 'IDENT'], 'units' => ['identifier'],
        ]]]], 'Invalid terminal witness: IDENT'];
        yield 'witness token is not a string' => [[...$valid, 'terminals' => ['IDENT' => [[
            'id' => 'id', 'sql' => 'name', 'tokens' => [1], 'units' => ['identifier'],
        ]]]], 'Invalid terminal witness: IDENT'];
        yield 'witness units is not an array' => [[...$valid, 'terminals' => ['IDENT' => [[
            'id' => 'id', 'sql' => 'name', 'tokens' => ['IDENT'], 'units' => 'identifier',
        ]]]], 'Invalid terminal witness: IDENT'];
        yield 'witness units is not a list' => [[...$valid, 'terminals' => ['IDENT' => [[
            'id' => 'id', 'sql' => 'name', 'tokens' => ['IDENT'], 'units' => [1 => 'identifier'],
        ]]]], 'Invalid terminal witness: IDENT'];
        yield 'witness unit is not a string' => [[...$valid, 'terminals' => ['IDENT' => [[
            'id' => 'id', 'sql' => 'name', 'tokens' => ['IDENT'], 'units' => [1],
        ]]]], 'Invalid terminal witness: IDENT'];
        yield 'witness context is not a string' => [[...$valid, 'terminals' => ['IDENT' => [[
            'id' => 'id', 'sql' => 'name', 'tokens' => ['IDENT'], 'units' => ['identifier'], 'context_sql' => 1,
        ]]]], 'Invalid terminal witness: IDENT'];

        yield 'terminal exclusion key is not a string' => [[...$valid, 'terminal_exclusions' => [0 => 'reason']], 'Terminal exclusions require string terminals and nonempty reasons.'];
        yield 'terminal exclusion reason is not a string' => [[...$valid, 'terminal_exclusions' => ['MODE' => 1]], 'Terminal exclusions require string terminals and nonempty reasons.'];
        yield 'terminal exclusion reason is empty' => [[...$valid, 'terminal_exclusions' => ['MODE' => '']], 'Terminal exclusions require string terminals and nonempty reasons.'];
        yield 'coverage units is not a list' => [[...$valid, 'coverage' => ['units' => [1 => 'identifier'], 'witnessed' => ['identifier' => 'ident.bare'], 'excluded' => []]], 'Coverage units must be a list of strings.'];
        yield 'coverage unit is not a string' => [[...$valid, 'coverage' => ['units' => [1], 'witnessed' => [], 'excluded' => [1 => 'reason']]], 'Coverage units must be a list of strings.'];
        yield 'coverage witnessed key is not a string' => [[...$valid, 'coverage' => ['units' => ['identifier'], 'witnessed' => [0 => 'ident.bare'], 'excluded' => ['identifier' => 'reason']]], 'Coverage witnesses require string units and identifiers.'];
        yield 'coverage witnessed id is not a string' => [[...$valid, 'coverage' => ['units' => ['identifier'], 'witnessed' => ['identifier' => 1], 'excluded' => []]], 'Coverage witnesses require string units and identifiers.'];
        yield 'coverage excluded key is not a string' => [[...$valid, 'coverage' => ['units' => ['identifier'], 'witnessed' => ['identifier' => 'ident.bare'], 'excluded' => [0 => 'reason']]], 'Coverage exclusions require string units and nonempty reasons.'];
        yield 'coverage excluded reason is not a string' => [[...$valid, 'coverage' => ['units' => ['identifier'], 'witnessed' => ['identifier' => 'ident.bare'], 'excluded' => ['other' => 1]]], 'Coverage exclusions require string units and nonempty reasons.'];
        yield 'coverage excluded reason is empty' => [[...$valid, 'coverage' => ['units' => ['identifier'], 'witnessed' => ['identifier' => 'ident.bare'], 'excluded' => ['other' => '']]], 'Coverage exclusions require string units and nonempty reasons.'];

        yield 'coverage units are duplicated' => [[...$valid, 'coverage' => ['units' => ['identifier', 'identifier'], 'witnessed' => ['identifier' => 'ident.bare'], 'excluded' => []]], 'coverage units must be unique'];
        yield 'coverage classifications overlap' => [[...$valid, 'coverage' => ['units' => ['identifier'], 'witnessed' => ['identifier' => 'ident.bare'], 'excluded' => ['identifier' => 'reason']]], 'both witnessed and excluded'];
        yield 'terminal is both supported and excluded' => [[...$valid, 'terminal_exclusions' => ['IDENT' => 'reason']], 'catalog and exclusions must be disjoint'];
        yield 'terminal has no witnesses' => [[...$valid, 'terminals' => ['IDENT' => []], 'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []]], 'Terminal catalog is empty: IDENT'];
        yield 'witness ids are duplicated' => [[...$valid, 'terminals' => [
            'IDENT' => [$valid['terminals']['IDENT'][0]],
            'STRING' => [[...$valid['terminals']['IDENT'][0], 'sql' => "'text'", 'tokens' => ['STRING']]],
        ]], 'Duplicate terminal witness identifier: ident.bare'];
        yield 'witness unit is unknown' => [[...$valid, 'terminals' => ['IDENT' => [[...$valid['terminals']['IDENT'][0], 'units' => ['unknown']]]]], 'unknown coverage unit: unknown'];
        yield 'coverage id is unknown' => [[...$valid, 'coverage' => ['units' => ['identifier'], 'witnessed' => ['identifier' => 'unknown'], 'excluded' => []]], 'unknown witness: identifier'];
        yield 'witness does not reference classified unit' => [[...$valid, 'terminals' => ['IDENT' => [[...$valid['terminals']['IDENT'][0], 'units' => []]]]], 'does not reference its unit: identifier'];
    }
}
