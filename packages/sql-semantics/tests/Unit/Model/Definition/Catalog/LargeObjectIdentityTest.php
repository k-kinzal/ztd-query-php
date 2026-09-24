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
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Catalog\LargeObjectIdentity::class)]
#[Medium]
final class LargeObjectIdentityTest extends TestCase
{
    #[TestWith([0])]
    #[TestWith([4294967295])]
    public function testAcceptsEveryUnsignedThirtyTwoBitIdentifier(int $id): void
    {
        self::assertSame($id, (new Catalog\LargeObjectIdentity($id))->id);
    }

    #[TestWith([-1])]
    #[TestWith([4294967296])]
    public function testRejectsAnIdentifierOutsideTheRange(int $id): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\LargeObjectIdentity($id);
    }

    public function testBindsAndWritesTheIdentifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('COMMENT ON LARGE OBJECT 16384 IS NULL');
        self::assertInstanceOf(Statement\CommentOnStatement::class, $statement);
        self::assertEquals(new Catalog\LargeObjectIdentity(16384), $statement->object);
        self::assertSame('COMMENT ON LARGE OBJECT 16384 IS NULL', $statement->toString());
    }
}
