<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyConstraintIdentity::class)]
#[Small]
final class ForeignKeyConstraintIdentityTest extends TestCase
{
    public function testConstraintRetainsItsQualifiedOwnerAndSpelling(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $symbol = new ForeignKeyConstraintIdentity($owner, new Identifier('Fk_1'));

        self::assertSame($owner, $symbol->owner);
        self::assertSame('Fk_1', $symbol->symbol->value);
    }
}
