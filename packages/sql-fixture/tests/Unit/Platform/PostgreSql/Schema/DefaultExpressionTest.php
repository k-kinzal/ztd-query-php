<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\DefaultExpression as Subject;

#[CoversClass(Subject::class)]
final class DefaultExpressionTest extends TestCase
{
    public function testExtractDefaultDistinguishesLiteralsAndExpressions(): void
    {
        $defaults = new Subject();
        self::assertSame(12, $defaults->extractDefault('INT DEFAULT 12 NOT NULL'));
        self::assertSame(12.5, $defaults->extractDefault('NUMERIC DEFAULT 12.5'));
        self::assertSame('ready', $defaults->extractDefault("TEXT DEFAULT 'ready'"));
        self::assertTrue($defaults->extractDefault('BOOLEAN DEFAULT TRUE'));
        self::assertNull($defaults->extractDefault('TEXT DEFAULT NULL'));
        self::assertSame('(1 + 2)', $defaults->extractDefault('INT DEFAULT (1 + 2)'));
        self::assertNull($defaults->extractDefault('TEXT'));
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDefaultClauses')]
    public function testExtractDefaultInterpretsLiteralKinds(string $clause, int|float|bool|string|null $expected): void
    {
        self::assertSame($expected, (new Subject())->extractDefault($clause));
    }

    /**
     * @return list<array{string, int|float|bool|string|null}>
     */
    public static function providerDefaultClauses(): array
    {
        return [
            ['BOOLEAN default false', false],
            ['BOOLEAN DEFAULT tRuE NOT NULL', true],
            ['TEXT DEFAULT nUlL', null],
            ['INT DEFAULT -12 CHECK (id > 0)', -12],
            ['DECIMAL DEFAULT -12.5 NOT NULL', -12.5],
            ['NUMERIC DEFAULT 1e3', 1000],
            ["TEXT DEFAULT 'a::b' UNIQUE", 'a::b'],
            ["TEXT DEFAULT 'line\nvalue'", "line\nvalue"],
            ["TEXT DEFAULT ''", ''],
            ['TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'],
            ['TIMESTAMP DEFAULT now()', 'now()'],
            ['INT DEFAULT (2 + 3)', '(2 + 3)'],
            ['TEXT', null],
            ["TEXT DEFAULT 'open'::text", "'open'::text"],
            ['INT DEFAULT 1', 1],
            ['INT DEFAULT 0', 0],
        ];
    }
}
