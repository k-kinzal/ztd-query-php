<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\ReindexObjectKind;
use SqlSemantics\Model\Statement\Maintenance\ReindexObjectStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReindexObjectStatement::class)]
final class ReindexObjectStatementTest extends TestCase
{
    #[TestWith(['REINDEX INDEX app.ix', ReindexObjectKind::Index, ['app', 'ix']])]
    #[TestWith(['REINDEX TABLE app.t', ReindexObjectKind::Table, ['app', 't']])]
    #[TestWith(['REINDEX SCHEMA app', ReindexObjectKind::Schema, ['app']])]
    public function testWithOriginRetainsTheRequiredNamedObject(string $sql, ReindexObjectKind $kind, array $name): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ReindexObjectStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($kind, $copy->targetKind);
        self::assertSame($name, $copy->target->parts);
        $roundTrip = $binder->bind($copy->toString());
        self::assertInstanceOf(ReindexObjectStatement::class, $roundTrip);
        self::assertSame($kind, $roundTrip->targetKind);
        self::assertSame($name, $roundTrip->target->parts);
    }
}
