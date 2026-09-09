<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage\Verification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\Verification\SqlContext;

#[CoversClass(SqlContext::class)]
#[UsesClass(CoverageException::class)]
final class SqlContextTest extends TestCase
{
    public function testSqlPreservesTheExactGeneratedFragmentAndBothWrappers(): void
    {
        $context = new SqlContext('SELECT ', ' AS value');
        self::assertSame('SELECT 1 AS value', $context->sql('1'));
        self::assertSame('1', $context->fragment('SELECT 1 AS value'));
        self::assertSame('SELECT  AS value', $context->sql(''));
        self::assertSame('', $context->fragment('SELECT  AS value'));
        self::assertSame('plain', (new SqlContext())->fragment('plain'));
    }

    #[DataProvider('providerMismatches')]
    public function testFragmentRejectsAChangedOrOverlappingWrapper(string $sql): void
    {
        $this->expectException(CoverageException::class);
        (new SqlContext('SELECT ', ' AS value'))->fragment($sql);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerMismatches(): array
    {
        return [['SELECT 1'], ['1 AS value'], ['SELECT 1 AS other'], ['']];
    }
}
