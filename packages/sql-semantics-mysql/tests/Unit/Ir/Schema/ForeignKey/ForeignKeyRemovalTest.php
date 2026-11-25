<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalKind;

#[CoversClass(ForeignKeyRemoval::class)]
#[Small]
final class ForeignKeyRemovalTest extends TestCase
{
    public function testToStringPreservesTheQuotedOriginalConstraint(): void
    {
        $action = new ForeignKeyRemoval(new Identifier('known`Ref'), ForeignKeyRemovalKind::Constraint);

        self::assertSame('DROP FOREIGN KEY `known``Ref`', $action->toString());
    }

    public function testToStringUsesAnEmptyPlaceholderForAnIgnoredTemporaryAction(): void
    {
        $action = new ForeignKeyRemoval(null, ForeignKeyRemovalKind::IgnoredOnTemporary);

        self::assertSame('DROP FOREIGN KEY ``', $action->toString());
    }
}
