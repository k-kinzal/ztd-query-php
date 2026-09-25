<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Ordering\UnresolvedOutputPosition;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

#[CoversClass(UnresolvedOutputPosition::class)]
#[Medium]
final class UnresolvedOutputPositionTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'SELECT "t".* FROM "public"."t" ORDER BY 2 ASC'])]
    #[TestWith([Dialect::MySql, 'SELECT `t`.* FROM `t` ORDER BY 2 ASC'])]
    public function testDefersAPositionBehindAnUnexpandedWildcard(Dialect $dialect, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT t.* FROM t ORDER BY 2', strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $key = $statement->orderBy[0]->key;
        self::assertInstanceOf(UnresolvedOutputPosition::class, $key);
        self::assertSame('2', $key->position->spelling);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testAcceptsLeadingZeros(): void
    {
        $position = new UnresolvedOutputPosition(new NumericParameter('007'));
        self::assertSame('007', $position->position->spelling);
    }

    #[TestWith(['0'])]
    #[TestWith(['-1'])]
    #[TestWith(['1.5'])]
    public function testRequiresAPositiveInteger(string $spelling): void
    {
        $this->expectException(InvalidStructure::class);
        new UnresolvedOutputPosition(new NumericParameter($spelling));
    }
}
