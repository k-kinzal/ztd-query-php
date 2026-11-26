<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Exchange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\ForeignKeyExchangeRemovals;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalKind;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyExchangeRemovals::class)]
#[Small]
final class ForeignKeyExchangeRemovalsTest extends TestCase
{
    public function testCheckAcceptsTheExactSymbolOfTheOwningTable(): void
    {
        $symbol = new Identifier('Previous');
        $removal = new ForeignKeyRemoval($symbol, ForeignKeyRemovalKind::Constraint);
        $owner = NativeAdditionFixture::owner('child');
        $existing = new ForeignKeyConstraintIdentity($owner, $symbol);
        $decoy = new ForeignKeyConstraintIdentity(NativeAdditionFixture::owner('different'), new Identifier('Decoy'));
        (new ForeignKeyExchangeRemovals())->check($owner, [$removal], [$decoy, $existing]);

        self::assertSame('DROP FOREIGN KEY `Previous`', $removal->toString());
    }
}
