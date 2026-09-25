<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Write\Assignment;
use SqlSemantics\Model\Write\Assignment\ScalarAssignment;
use SqlSemantics\Model\Write\Storage\ColumnPath;
use SqlSemantics\Model\Write\Storage\ElementPath;
use SqlSemantics\Model\Write\Storage\FieldPath;
use SqlSemantics\Model\Write\Storage\SlicePath;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Write\StoragePaths;

#[CoversClass(StoragePaths::class)]
#[Medium]
final class StoragePathsTest extends TestCase
{
    public function testWriteSerializesEveryPathFormWithoutEvaluatingIt(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, a INT[], r JSONB)'));
        $statement = $binder->bind('UPDATE t SET a[1:2] = a, a[1] = 2, r.f = 1, id = 1, a[:2] = a, a[1:] = a');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertSame(['"a"[1 : 2]', '"a"[1]', '"r"."f"', '"id"', '"a"[: 2]', '"a"[1 :]'], array_map(static fn (Assignment $assignment): string => StoragePaths::write($assignment->destinations()[0])->toString(), $statement->writes));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWriteNestsAFieldBeneathAnElement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT[])')))->bind('UPDATE t SET a[1] = 2');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $element = $statement->writes[0]->target;
        self::assertInstanceOf(ElementPath::class, $element);
        self::assertInstanceOf(ColumnPath::class, $element->base);
        $nested = new FieldPath($element, 'g');
        self::assertSame('"a"[1]."g"', StoragePaths::write($nested)->toString());
        $slice = new SlicePath($element->base, Expression::literal(1, Dialect::PostgreSql), null);
        self::assertSame('"a"[1 :]', StoragePaths::write($slice)->toString());
    }

    public function testWriteQuotesAnUnresolvedColumnForDiagnostics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('UPDATE t SET missing = 1', strict: false);
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        self::assertInstanceOf(ColumnPath::class, $statement->writes[0]->target);
        self::assertSame('`missing`', StoragePaths::write($statement->writes[0]->target)->toString());
        self::assertSame('unknown-column', $statement->diagnostics[0]->reason);
    }
}
