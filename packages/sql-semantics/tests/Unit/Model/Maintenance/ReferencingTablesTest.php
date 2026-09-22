<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\ReferencingTables;
use SqlSemantics\Model\Statement\Maintenance\TruncateRelationsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReferencingTables::class)]
#[Medium]
final class ReferencingTablesTest extends TestCase
{
    #[TestWith(['CASCADE', ReferencingTables::Include])]
    #[TestWith(['RESTRICT', ReferencingTables::RequireListed])]
    public function testClassifiesForeignKeyDependencyPolicy(string $sql, ReferencingTables $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('TRUNCATE t ' . $sql);
        self::assertInstanceOf(TruncateRelationsStatement::class, $statement);
        self::assertSame($expected, $statement->references);
        self::assertSame('TRUNCATE TABLE "public"."t" CONTINUE IDENTITY ' . $sql, $statement->toString());
    }
}
