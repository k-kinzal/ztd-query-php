<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\Wildcard;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Nullability;

#[CoversClass(Wildcard::class)]
#[Medium]
final class WildcardTest extends TestCase
{
    public function testInputsHasNoOperandsForAnUnexpandedWildcard(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind('SELECT m.* FROM missing m', strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $wildcard = $statement->outputs[0]->expression;
        self::assertInstanceOf(Wildcard::class, $wildcard);
        self::assertSame(['m'], $wildcard->qualifier);
        self::assertSame([], $wildcard->inputs());
        self::assertSame(BuiltinIdentity::Unknown, $wildcard->type->identity);
        self::assertSame(Nullability::Unknown, $wildcard->nullability);
        self::assertSame('SELECT "m".* FROM "main"."missing" AS "m"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testReferencePartsReturnsTheQualifier(): void
    {
        $origin = Expression::reference(['t'], Dialect::PostgreSql);
        $qualified = new Wildcard($origin->facts, $origin->source, ['s', 't']);
        $bare = new Wildcard($origin->facts, $origin->source, []);
        self::assertSame(['s', 't'], $qualified->referenceParts());
        self::assertSame('"s"."t".*', $qualified->structure()->toString());
        self::assertSame([], $bare->referenceParts());
        self::assertSame('*', $bare->structure()->toString());
    }

    public function testSpellingIsTheAsterisk(): void
    {
        $origin = Expression::reference(['t'], Dialect::PostgreSql);
        $wildcard = new Wildcard($origin->facts, $origin->source, ['t']);
        self::assertSame('*', $wildcard->spelling());
    }

    public function testWithFactsKeepsTheQualifier(): void
    {
        $origin = Expression::reference(['t'], Dialect::PostgreSql);
        $wildcard = new Wildcard($origin->facts, $origin->source, ['t']);
        $copy = $wildcard->withFacts(new ExpressionFacts($wildcard->type, Nullability::MaybeNull));
        self::assertNotSame($wildcard, $copy);
        self::assertSame(['t'], $copy->qualifier);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::Unknown, $wildcard->nullability);
    }
}
