<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ObjectAddress::class)]
#[Medium]
final class ObjectAddressTest extends TestCase
{
    /**
     * @param class-string<object> $identity
     */
    #[TestWith(["COMMENT ON SCHEMA app IS 'x'", \SqlSemantics\Model\Definition\Catalog\NamedIdentity::class])]
    #[TestWith(["COMMENT ON TABLE app.t IS 'x'", \SqlSemantics\Model\Definition\Catalog\RelationIdentity::class])]
    #[TestWith(["COMMENT ON COLLATION app.c IS 'x'", \SqlSemantics\Model\Definition\Catalog\SchemaObjectIdentity::class])]
    #[TestWith(["COMMENT ON CAST (integer AS text) IS 'x'", \SqlSemantics\Model\Definition\Catalog\CastIdentity::class])]
    #[TestWith(["COMMENT ON OPERATOR CLASS c USING btree IS 'x'", \SqlSemantics\Model\Definition\Catalog\OperatorSetIdentity::class])]
    public function testEachObjectClassSelectsItsOwnIdentityShape(string $sql, string $identity): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(CommentOnStatement::class, $statement);
        self::assertInstanceOf($identity, $statement->object);
    }
}
