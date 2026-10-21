<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Dml\Update\Publication;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Publication::class)]
#[Small]
final class PublicationTest extends TestCase
{
    public function testCreatePreservesTheOriginalOrdinaryOperands(): void
    {
        $table = new M\Relation\TableReference(new QualifiedName(new Name('t')));
        $assignment = new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a')), new M\Literal\NumberLiteral('1'));
        $order = new M\Query\OrderItem(new M\Name\ColumnUse(new Name('a')));
        $write = (new Publication())->create(null, false, false, [$table], [$assignment], null, [$order], null, []);
        self::assertSame([$table], $write->tables);
        self::assertSame([$assignment], $write->assignments);
        self::assertSame([$order], $write->orderBy);
    }
}
