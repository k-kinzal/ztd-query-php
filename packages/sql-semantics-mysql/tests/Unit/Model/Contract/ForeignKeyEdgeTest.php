<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\ForeignKeyEdge;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;

#[CoversClass(ForeignKeyEdge::class)]
#[Medium]
final class ForeignKeyEdgeTest extends TestCase
{
    public function testRetainsEachDatabaseCoordinate(): void
    {
        $child = new QualifiedName(new Name('child'), new Name('secondary'));
        $parent = new QualifiedName(new Name('parent'), new Name('primary'));
        $edge = new ForeignKeyEdge($child, $parent);

        self::assertSame($child, $edge->child);
        self::assertSame($parent, $edge->parent);
    }
}
