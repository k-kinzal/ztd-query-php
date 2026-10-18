<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Existence;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Existence::class)]
#[Medium]
final class ExistenceTest extends TestCase
{
    public function testMarkIncludesSetBranchesButLeavesDerivedTablesUnchanged(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $query = $semantics->analyze('SELECT * FROM (SELECT 1,2) t UNION (SELECT 3)')->statement;
        self::assertInstanceOf(QueryRoot::class, $query);
        $derivation = new Derivation($semantics->context());
        (new Existence())->mark($query->query, $derivation);
        self::assertCount(2, $derivation->existenceQueries);
        $fact = $derivation->query($query->query, $derivation->environment());
        self::assertCount(1, $fact->projection);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testMarkDoesNotChangeAnInnerDerivedProjection(): void
    {
        $inner = new Select([], [new Star()]);
        $outer = new Select([], [new Star()], new DerivedTable($inner, new Name('t')));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        (new Existence())->mark($outer, $derivation);
        self::assertTrue($derivation->existenceQueries->offsetExists($outer));
        self::assertFalse($derivation->existenceQueries->offsetExists($inner));
    }
}
