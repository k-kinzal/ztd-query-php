<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Catalog\RoutineIdentity::class)]
#[Medium]
final class RoutineIdentityTest extends TestCase
{
    /**
     * @param class-string<object> $target
     */
    #[TestWith(['COMMENT ON FUNCTION app.f(integer) IS NULL', Kind\RoutineKind::Function, \SqlSemantics\Model\Definition\Routine\RoutineBySignature::class])]
    #[TestWith(['COMMENT ON PROCEDURE p IS NULL', Kind\RoutineKind::Procedure, \SqlSemantics\Model\Definition\Routine\RoutineByName::class])]
    #[TestWith(['COMMENT ON ROUTINE r() IS NULL', Kind\RoutineKind::Routine, \SqlSemantics\Model\Definition\Routine\RoutineBySignature::class])]
    public function testRetainsTheRoutineClassAndSelection(string $sql, Kind\RoutineKind $kind, string $target): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(Statement\CommentOnStatement::class, $statement);
        self::assertInstanceOf(Catalog\RoutineIdentity::class, $statement->object);
        self::assertSame($kind, $statement->object->kind);
        self::assertInstanceOf($target, $statement->object->target);
    }
}
