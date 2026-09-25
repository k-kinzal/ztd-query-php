<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Identity\SequenceValueChange::class)]
#[Medium]
final class SequenceValueChangeTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET INCREMENT BY 5', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Identity\SetColumnIdentity::class, $statement->actions[0]);
        self::assertInstanceOf(Relation\Identity\SequenceValueChange::class, $statement->actions[0]->changes[0]);
        self::assertSame(Relation\Identity\SequenceAttribute::Increment, $statement->actions[0]->changes[0]->attribute);
        self::assertSame('5', $statement->actions[0]->changes[0]->value->text);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" SET INCREMENT BY 5', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAFractionalValue(): void
    {
        $literal = \SqlSemantics\Model\Expression::literal(1.5, Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        new Relation\Identity\SequenceValueChange(Relation\Identity\SequenceAttribute::Start, $literal);
    }
}
