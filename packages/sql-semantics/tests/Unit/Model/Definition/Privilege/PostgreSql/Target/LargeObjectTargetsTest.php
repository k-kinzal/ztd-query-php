<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\LargeObjectTargets;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LargeObjectTargets::class)]
#[Medium]
final class LargeObjectTargetsTest extends TestCase
{
    public function testRetainsTheIdentifiersInRequestOrder(): void
    {
        self::assertSame([13, 12], (new LargeObjectTargets([13, 12]))->ids);
        self::assertSame([0, 4294967295], (new LargeObjectTargets([0, 4294967295]))->ids);
    }

    public function testReadsTheIdentifiersOfABoundGrant(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT SELECT, UPDATE ON LARGE OBJECT 12, 13 TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals(new LargeObjectTargets([12, 13]), $statement->target);
        self::assertSame('GRANT SELECT, UPDATE ON LARGE OBJECT 12, 13 TO "a"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    #[TestWith([-1])]
    #[TestWith([4294967296])]
    public function testRejectsAnIdentifierOutsideTheOidRange(int $id): void
    {
        $this->expectException(InvalidStructure::class);
        new LargeObjectTargets([12, $id]);
    }
}
