<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\ExplicitIndexOrder;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ExplicitIndexOrder::class)]
#[Small]
final class ExplicitIndexOrderTest extends TestCase
{
    public function testMessageDescribesTheRetainedDirectionConflict(): void
    {
        $problem = new ExplicitIndexOrder(IndexKind::FullText, Direction::Descending, new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')));
        self::assertSame(Direction::Descending, $problem->direction);
        self::assertSame('Incorrect usage of spatial/fulltext/hash index and explicit index order', $problem->message());
    }

    public function testRejectsAnOrdinaryOrderedIndex(): void
    {
        $this->expectExceptionMessage('An index ordering condition requires an incompatible direction.');
        new ExplicitIndexOrder(IndexKind::Index, Direction::Descending, new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')));
    }
}
