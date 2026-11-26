<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ExclusionIndexReference;

#[CoversClass(ExclusionIndexReference::class)]
#[Small]
final class ExclusionIndexReferenceTest extends TestCase
{
    public function testQuotedPreservesTheExactNamespaceAndIndex(): void
    {
        $schema = new Identifier('calendar.data');
        $index = new Identifier('予定"重複');
        $table = new Identifier('bookings');
        $value = new ExclusionIndexReference($schema, $index, $table);

        self::assertSame('"calendar.data"."予定""重複"', $value->quoted());
        self::assertSame($table, $value->table);
    }
}
