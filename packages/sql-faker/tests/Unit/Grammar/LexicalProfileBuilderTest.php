<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalProfileBuilder;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\TerminalInventory;

#[CoversClass(LexicalProfileBuilder::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(TerminalInventory::class)]
final class LexicalProfileBuilderTest extends TestCase
{
    #[DataProvider('providerMySqlPunctuation')]
    public function testMySqlCatalogPreservesPunctuation(string $punctuation): void
    {
        /** @var array{terminals: array<string, list<array{sql: string, tokens: list<string>}>>} $catalog */
        $catalog = (new ReflectionMethod(LexicalProfileBuilder::class, 'mySqlCatalog'))->invoke(
            new LexicalProfileBuilder(),
            'mysql-8.4.7',
            ['symbols' => [], 'functions' => [], 'features' => ['dollar_quoted_strings' => false]],
            [],
            new Grammar('start', []),
        );

        self::assertArrayHasKey($punctuation, $catalog['terminals']);
        self::assertSame($punctuation, $catalog['terminals'][$punctuation][0]['sql']);
        self::assertSame([$punctuation], $catalog['terminals'][$punctuation][0]['tokens']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerMySqlPunctuation(): iterable
    {
        foreach (['!', '%', '&', '(', ')', '*', '+', ',', '-', '.', '/', ':', ';', '<', '=', '>', '?', '@', '[', ']', '^', '{', '}', '|', '~'] as $punctuation) {
            yield $punctuation => [$punctuation];
        }
    }

    public function testSqliteCatalogPreservesUnicodeIdentifierWitness(): void
    {
        /** @var array{catalog: array{source: array{character_classes: list<string>}}} $profile */
        $profile = require __DIR__ . '/../../../resources/lexical/sqlite-3.47.2.php';

        /** @var array{terminals: array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>}>>} $catalog */
        $catalog = (new ReflectionMethod(LexicalProfileBuilder::class, 'sqliteCatalog'))->invoke(
            new LexicalProfileBuilder(),
            ['keywords' => []],
            $profile['catalog']['source']['character_classes'],
        );
        $witnesses = array_column($catalog['terminals']['@COVERAGE'], null, 'id');

        self::assertArrayHasKey('cc-id', $witnesses);
        self::assertSame([
            'id' => 'cc-id',
            'sql' => 'é',
            'tokens' => ['TK_ID'],
            'units' => ['CC_ID'],
        ], $witnesses['cc-id']);
    }

    public function testAcceptsACompleteVersionBoundProfile(): void
    {
        $this->expectNotToPerformAssertions();

        $profile = [
            'dialect' => 'test',
            'version' => 'test-1',
            'catalog' => [
                'source' => ['engine' => 'test', 'entrypoint' => 'lexer'],
                'terminals' => [
                    'TOKEN' => [[
                        'id' => 'token',
                        'sql' => 'token',
                        'tokens' => ['TOKEN'],
                        'units' => ['branch:1'],
                    ]],
                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => ['branch:1'],
                    'witnessed' => ['branch:1' => 'token'],
                    'excluded' => [],
                ],
            ],
        ];

        (new LexicalProfileBuilder())->assertCompatible($profile, 'test', 'test-1', ['TOKEN']);
    }

    public function testRejectsAMismatchedProfileIdentity(): void
    {
        $this->expectException(RuntimeException::class);

        (new LexicalProfileBuilder())->assertCompatible([], 'test', 'test-1', []);
    }

    /**
     * @param array<string, mixed> $profile
     */
    #[DataProvider('providerInvalidProfile')]
    public function testRejectsInvalidProfile(array $profile, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new LexicalProfileBuilder())->assertCompatible($profile, 'test', 'test-1', ['TOKEN']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function providerInvalidProfile(): iterable
    {
        yield 'dialect mismatch' => [[
            'dialect' => 'other',
            'version' => 'test-1',
        ], 'Invalid lexical profile identity: test test-1'];
        yield 'version mismatch' => [[
            'dialect' => 'test',
            'version' => 'test-2',
        ], 'Invalid lexical profile identity: test test-1'];
        yield 'missing catalog' => [[
            'dialect' => 'test',
            'version' => 'test-1',
        ], 'Lexical profile catalog is missing: test test-1'];
        yield 'catalog is not an array' => [[
            'dialect' => 'test',
            'version' => 'test-1',
            'catalog' => 'invalid',
        ], 'Lexical profile catalog is missing: test test-1'];
        yield 'catalog misses a terminal' => [[
            'dialect' => 'test',
            'version' => 'test-1',
            'catalog' => [
                'source' => ['engine' => 'test', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ],
        ], 'missing grammar terminals: TOKEN'];
    }
}
