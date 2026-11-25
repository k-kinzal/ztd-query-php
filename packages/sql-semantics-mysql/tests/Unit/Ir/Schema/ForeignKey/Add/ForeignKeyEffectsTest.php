<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAction;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyEffects;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyMatch;

#[CoversClass(ForeignKeyEffects::class)]
#[Small]
final class ForeignKeyEffectsTest extends TestCase
{
    public function testSqlKeepsIndependentActionRoles(): void
    {
        $effects = new ForeignKeyEffects(ForeignKeyAction::Cascade, ForeignKeyAction::SetNull, ForeignKeyMatch::Partial);

        self::assertSame(' MATCH PARTIAL ON UPDATE CASCADE ON DELETE SET NULL', $effects->sql());
    }
}
