<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceName;

#[CoversClass(SequenceName::class)]
final class SequenceNameTest extends TestCase
{
    public function testQuotedDelimitsBothCoordinates(): void
    {
        $name = new SequenceName(new Identifier('schema.棚'), new Identifier('name"part'));

        self::assertSame('"schema.棚"."name""part"', $name->quoted());
    }
}
