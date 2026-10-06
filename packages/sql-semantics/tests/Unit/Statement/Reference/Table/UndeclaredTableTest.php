<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(UndeclaredTable::class)]
#[Medium]
final class UndeclaredTableTest extends TestCase
{
    public function testMissingNamesTheRelationAnOpenContextDoesNotDeclare(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT a FROM s.t');
        $fact = $query->facts->relation($query->singleNamedInput());

        self::assertInstanceOf(UndeclaredTable::class, $fact->table);
        self::assertSame('the declaration of relation s.t', $fact->table->missing->describe());
        self::assertSame([$fact->table->missing], $fact->shape->missing);
        self::assertSame([], $query->facts->diagnostics);
    }
}
