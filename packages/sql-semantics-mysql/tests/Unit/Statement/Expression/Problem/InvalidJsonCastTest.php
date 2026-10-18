<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

#[CoversClass(InvalidJsonCast::class)]
#[Small]
final class InvalidJsonCastTest extends TestCase
{
    public function testMessageNamesTheSpatialResultType(): void
    {
        self::assertSame('Invalid cast from JSON to MULTIPOINT.', (new InvalidJsonCast(CastKind::MultiPoint))->message());
    }
}
