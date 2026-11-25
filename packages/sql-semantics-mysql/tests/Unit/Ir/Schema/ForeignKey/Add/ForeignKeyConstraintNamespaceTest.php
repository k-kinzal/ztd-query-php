<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyConstraintNamespace;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyConstraintNamespace::class)]
#[Small]
final class ForeignKeyConstraintNamespaceTest extends TestCase
{
    public function testAllocateContinuesFromTheOwningTableOrdinal(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $catalog = [new ForeignKeyConstraintIdentity($owner, new Identifier('child_ibfk_8'))];
        $symbols = (new ForeignKeyConstraintNamespace())->allocate($owner, $catalog, [null, new Identifier('Named'), null]);

        self::assertSame(['child_ibfk_9', 'Named', 'child_ibfk_10'], array_column($symbols, 'value'));
    }

    public function testOrdinalIgnoresNoncanonicalMarkers(): void
    {
        $names = new ForeignKeyConstraintNamespace();

        self::assertSame(0, $names->ordinal('child_ibfk_014', 'child_ibfk_'));
        self::assertSame(15, $names->ordinal('child_ibfk_15', 'child_ibfk_'));
    }
}
