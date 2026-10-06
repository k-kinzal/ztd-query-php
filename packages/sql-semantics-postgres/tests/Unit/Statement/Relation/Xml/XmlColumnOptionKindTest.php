<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind::class)]
#[Small]
final class XmlColumnOptionKindTest extends TestCase
{
    public function testValuedTellsWhichOptionsHaveAValue(): void
    {
        self::assertSame([true, true, false, false, true], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind $kind): bool => $kind->valued(), \SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind::cases()));
    }
}
