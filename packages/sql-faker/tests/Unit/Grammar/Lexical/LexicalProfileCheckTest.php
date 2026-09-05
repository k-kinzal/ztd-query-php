<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Lexical\LexicalCatalogShape;
use SqlFaker\Grammar\Lexical\LexicalCoverageCheck;
use SqlFaker\Grammar\Lexical\LexicalProfileCheck;
use SqlFaker\Grammar\Lexical\LexicalWitnessCheck;
use SqlFaker\Grammar\Lexical\LexicalWitnessShape;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalCatalogException;

#[CoversClass(LexicalProfileCheck::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(LexicalCatalogException::class)]
#[UsesClass(LexicalCatalogShape::class)]
#[UsesClass(LexicalCoverageCheck::class)]
#[UsesClass(LexicalWitnessCheck::class)]
#[UsesClass(LexicalWitnessShape::class)]
final class LexicalProfileCheckTest extends TestCase
{
    public function testAssertCompatibleAcceptsAProfileThatClassifiesEveryTerminal(): void
    {
        (new LexicalProfileCheck())->assertCompatible(
            [
                'dialect' => 'mysql',
                'version' => 'mysql-8.4.7',
                'catalog' => [
                    'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                    'terminals' => [
                        'IDENT' => [[
                            'id' => 'ident.bare',
                            'sql' => 'users',
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
                ],
            ],
            'mysql',
            'mysql-8.4.7',
            ['IDENT'],
        );

        $this->expectNotToPerformAssertions();
    }

    /**
     * @param array<string, mixed> $profile
     */
    #[DataProvider('providerInvalidIdentities')]
    public function testAssertCompatibleRejectsAnInvalidReleaseIdentity(array $profile): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid lexical profile identity: mysql mysql-8.4.7');

        (new LexicalProfileCheck())->assertCompatible(
            $profile,
            'mysql',
            'mysql-8.4.7',
            [],
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function providerInvalidIdentities(): iterable
    {
        yield 'other version' => [['dialect' => 'mysql', 'version' => 'mysql-8.0.44']];
        yield 'other dialect' => [['dialect' => 'postgresql', 'version' => 'mysql-8.4.7']];
        yield 'missing dialect' => [['version' => 'mysql-8.4.7']];
        yield 'missing version' => [['dialect' => 'mysql']];
    }

    /**
     * @param array<string, mixed> $catalog
     */
    #[DataProvider('providerInvalidCatalogs')]
    public function testAssertCompatibleRejectsAMissingOrMalformedCatalog(array $catalog): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lexical profile catalog is missing: mysql mysql-8.4.7');

        (new LexicalProfileCheck())->assertCompatible(
            ['dialect' => 'mysql', 'version' => 'mysql-8.4.7', ...$catalog],
            'mysql',
            'mysql-8.4.7',
            [],
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function providerInvalidCatalogs(): iterable
    {
        yield 'missing' => [[]];
        yield 'null' => [['catalog' => null]];
        yield 'string' => [['catalog' => 'invalid']];
    }

    public function testAssertCompatibleRejectsAnUnaccountedGrammarTerminal(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('UNACCOUNTED');

        (new LexicalProfileCheck())->assertCompatible(
            [
                'dialect' => 'sqlite',
                'version' => 'sqlite-3.47.2',
                'catalog' => [
                    'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                    'terminals' => [],
                    'terminal_exclusions' => [],
                    'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
                ],
            ],
            'sqlite',
            'sqlite-3.47.2',
            ['UNACCOUNTED'],
        );
    }

}
