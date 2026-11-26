<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexReference;

#[CoversClass(IndexReference::class)]
#[Small]
final class IndexReferenceTest extends TestCase
{
    public function testQuotedSpellsTheIndexCoordinateWithoutUsingTheOwnerAsAnOperand(): void
    {
        $target = new IndexReference(new Identifier('pg_temp'), new Identifier('label index'), new Identifier('data'));

        self::assertSame('"pg_temp"."label index"', $target->quoted());
        self::assertSame('data', $target->table->value);
    }
}
