<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Rename;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRenameNamespace;

#[CoversClass(IndexRenameNamespace::class)]
#[Medium]
final class IndexRenameNamespaceTest extends TestCase
{
    public function testCheckAcceptsAnOccupiedNameWhenItsOriginalKeyMoves(): void
    {
        $left = new Identifier('left_key');
        $right = new Identifier('right_key');
        $moves = [new IndexRename($left, $right), new IndexRename($right, $left)];

        (new IndexRenameNamespace())->check($moves, [new Identifier('PRIMARY')]);

        self::assertCount(2, $moves);
    }
}
