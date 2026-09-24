<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Identity\IdentityInvariant::class)]
#[Medium]
final class IdentityInvariantTest extends TestCase
{
    public function testIntegerAcceptsSignedIntegerSpellings(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET MINVALUE -3');
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Identity\SetColumnIdentity::class, $statement->actions[0]);
        self::assertInstanceOf(Relation\Identity\SequenceValueChange::class, $statement->actions[0]->changes[0]);
        self::assertSame('-3', $statement->actions[0]->changes[0]->value->text);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" SET MINVALUE -3', $statement->toString());
        Relation\Identity\IdentityInvariant::integer($statement->actions[0]->changes[0]->value);
    }

    #[TestWith([1.5, Dialect::PostgreSql])]
    #[TestWith([1, Dialect::MySql])]
    public function testIntegerRejectsOtherLiterals(int|float $value, Dialect $dialect): void
    {
        $literal = \SqlSemantics\Model\Expression::literal($value, $dialect);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        Relation\Identity\IdentityInvariant::integer($literal);
    }
}
