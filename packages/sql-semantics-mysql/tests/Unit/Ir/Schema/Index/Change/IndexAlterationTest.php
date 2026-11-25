<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Change;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\IndexAlteration;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename;

#[CoversClass(IndexAlteration::class)]
#[Medium]
final class IndexAlterationTest extends TestCase
{
    public function testToStringEmitsTheOriginalMoveOperands(): void
    {
        $move = new IndexRename(new Identifier('old'), new Identifier('new'));
        $action = new IndexAlteration($move);

        self::assertSame('RENAME INDEX `old` TO `new`', $action->toString());
    }
}
