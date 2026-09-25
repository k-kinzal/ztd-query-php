<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Write\Assignment;
use SqlSemantics\Model\Write\Storage\ColumnPath;
use SqlSemantics\Model\Write\Storage\ElementPath;
use SqlSemantics\Model\Write\Storage\FieldPath;
use SqlSemantics\Model\Write\Storage\Path;
use SqlSemantics\Model\Write\Storage\SlicePath;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Path::class)]
#[Medium]
final class PathTest extends TestCase
{
    public function testColumnIdentifiesTheStoredColumnOfEveryPathForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER[], r JSONB)')))->bind('UPDATE t SET a[1:2]=a, a[1]=2, r.f=1, id=1');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        $paths = array_map(static fn (Assignment $assignment): Path => $assignment->destinations()[0], $statement->writes);
        self::assertSame([SlicePath::class, ElementPath::class, FieldPath::class, ColumnPath::class], array_map(static fn (Path $path): string => $path::class, $paths));
        self::assertSame(['a', 'a', 'r', 'id'], array_map(static fn (Path $path): ?string => $path->column()->columnBinding()?->column->name, $paths));
        self::assertSame('UPDATE "public"."t" SET "a"[1 : 2] = "a", "a"[1] = 2, "r"."f" = 1, "id" = 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testTypeDescribesTheDestinationWithoutLoadingAValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER[], r JSONB)')))->bind('UPDATE t SET a[1:2]=a, a[1]=2, r.f=1, id=1');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        $paths = array_map(static fn (Assignment $assignment): Path => $assignment->destinations()[0], $statement->writes);
        self::assertSame(['integer[]', 'integer', 'unknown', 'integer'], array_map(static fn (Path $path): string => $path->type()->name, $paths));
        self::assertSame([Dialect::PostgreSql, Dialect::PostgreSql, Dialect::PostgreSql, Dialect::PostgreSql], array_map(static fn (Path $path): Dialect => $path->type()->dialect, $paths));
    }
}
