<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Type\Identity\LabelSet::class)]
#[Medium]
final class LabelSetTest extends TestCase
{
    public function testNameRetainsTheLabelFamilyAndSerializationKeepsItsEncoding(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE TABLE t(x SET(0x41, \'b\') ASCII BINARY)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $statement);
        $identity = $statement->definition->table->columns[0]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\LabelSet::class, $identity);
        self::assertSame('set', $identity->name());
        self::assertSame('latin1', $identity->characterSet);
        self::assertTrue($identity->binary);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsNumericLiteralsAsLabels(): void
    {
        $literal = Expression::literal(12, Dialect::MySql);
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Type\Identity\LabelSet([$literal]);
    }

}
