<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Affinity;
use SqlSemantics\Statement\Type\SqliteCastTarget;

#[CoversClass(SqliteCastTarget::class)]
#[Small]
final class SqliteCastTargetTest extends TestCase
{
    #[TestWith(['', Affinity::Numeric])]
    #[TestWith(['FLOATING POINT', Affinity::Integer])]
    #[TestWith(['CHARBLOB', Affinity::Text])]
    #[TestWith(['BLOBREAL', Affinity::Blob])]
    #[TestWith(['DOUBLE PRECISION', Affinity::Real])]
    #[TestWith(['BOOLEAN', Affinity::Numeric])]
    #[TestWith(['A /* INT */ B', Affinity::Integer])]
    public function testAffinityUsesTheDatabaseSubstringPrecedence(string $name, Affinity $expected): void
    {
        $target = new SqliteCastTarget($name);
        self::assertSame($name, $target->name);
        self::assertSame($expected, $target->affinity);
    }

    public function testToStringQuotesTheDecodedTypeName(): void
    {
        self::assertSame('"a""b"', (new SqliteCastTarget('a"b'))->toString());
        self::assertSame('""', (new SqliteCastTarget(''))->toString());
    }
}
