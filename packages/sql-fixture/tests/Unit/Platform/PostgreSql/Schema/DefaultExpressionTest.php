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
}
