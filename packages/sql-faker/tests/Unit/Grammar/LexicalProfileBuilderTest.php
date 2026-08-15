<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalProfileBuilder;

#[CoversClass(LexicalProfileBuilder::class)]
#[UsesClass(LexicalCatalog::class)]
final class LexicalProfileBuilderTest extends TestCase
{
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
