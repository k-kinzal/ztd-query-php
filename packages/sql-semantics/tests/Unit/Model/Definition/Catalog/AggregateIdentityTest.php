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
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Catalog\AggregateIdentity::class)]
#[Medium]
final class AggregateIdentityTest extends TestCase
{
    /**
     * @param class-string<object> $form
     */
    #[TestWith(['ALTER AGGREGATE app.count_all(*) OWNER TO alice', \SqlSemantics\Model\Definition\Routine\ZeroArgumentAggregate::class, 'ALTER AGGREGATE "app"."count_all"(*) OWNER TO "alice"'])]
    #[TestWith(['ALTER AGGREGATE total(integer) OWNER TO alice', \SqlSemantics\Model\Definition\Routine\OrdinaryAggregate::class, 'ALTER AGGREGATE "total"(integer) OWNER TO "alice"'])]
    public function testRetainsTheSignatureFormOfTheAggregate(string $sql, string $form, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(Statement\ChangeObjectOwnerStatement::class, $statement);
        self::assertInstanceOf(Catalog\AggregateIdentity::class, $statement->object);
        self::assertInstanceOf($form, $statement->object->target);
        self::assertSame($expected, $statement->toString());
    }
}
