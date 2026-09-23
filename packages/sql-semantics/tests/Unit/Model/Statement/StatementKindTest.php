<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StatementKind::class)]
#[Medium]
final class StatementKindTest extends TestCase
{
    public function testEveryOperationSpellsItsOwnKeyword(): void
    {
        $values = array_column(StatementKind::cases(), 'value');
        self::assertSame(array_map(strtoupper(...), $values), $values);
        self::assertSame($values, array_unique($values));
        self::assertContains('REFRESH', $values);
    }

    public function testAConcreteStatementDeterminesItsOperation(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)'));
        self::assertSame(StatementKind::Select, $binder->bind('SELECT a FROM t')->kind);
        self::assertSame(StatementKind::Insert, $binder->bind('INSERT INTO t VALUES (1)')->kind);
        self::assertSame(StatementKind::Refresh, $binder->bind('REFRESH MATERIALIZED VIEW m')->kind);
    }

}
