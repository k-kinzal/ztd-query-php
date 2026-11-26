<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyPair;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyPair::class)]
#[Small]
final class ForeignKeyPairTest extends TestCase
{
    public function testPairKeepsResolvedRolesAndPhysicalDirection(): void
    {
        $pair = NativeAdditionFixture::pair('child_id', 'id', descending: true);

        self::assertSame('child_id', $pair->child->value);
        self::assertSame('id', $pair->parent->value);
        self::assertTrue($pair->descending);
    }
}
