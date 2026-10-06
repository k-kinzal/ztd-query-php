<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Alignment;

#[CoversClass(Alignment::class)]
#[Small]
final class AlignmentTest extends TestCase
{
    public function testReadAcceptsTheTranslatedTypeNames(): void
    {
        self::assertSame([Alignment::Double, Alignment::Double, Alignment::Int, Alignment::Short, Alignment::Char], [Alignment::read('DOUBLE'), Alignment::read('pg_catalog.float8'), Alignment::read('pg_catalog.int4'), Alignment::read('int2'), Alignment::read('pg_catalog.bpchar')]);
    }

    public function testReadRejectsOtherWords(): void
    {
        self::assertNull(Alignment::read('int8'));
    }
}
