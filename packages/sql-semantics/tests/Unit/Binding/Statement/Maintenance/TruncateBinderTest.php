<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Maintenance\TruncateBinder::class)]
#[Medium]
final class TruncateBinderTest extends TestCase
{
    public function testBindKeepsUnresolvedQualifiedTargetsForDiagnostics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('TRUNCATE ONLY app.missing', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\TruncateRelationsStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\OnlyTableReference::class, $statement->tables[0]);
        self::assertSame(['app', 'missing'], $statement->tables[0]->name->parts);
        self::assertFalse($statement->tables[0]->declaration->resolved);
        self::assertSame('unknown-table', $statement->diagnostics[0]->reason);
    }
}
