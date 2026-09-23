<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Transaction\Xa\BranchIdentifier;
use SqlSemantics\SchemaBuilder;

#[CoversClass(BranchIdentifier::class)]
#[Medium]
final class BranchIdentifierTest extends TestCase
{
    public function testRetainsOnlyClassifiedIdentifierComponents(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("XA START 'g', 'b', 42");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\Xa\XaStartStatement::class, $statement);
        $id = $statement->transactionId->branch;
        self::assertInstanceOf(BranchIdentifier::class, $id);
        self::assertSame("'b'", $id->qualifier->text);
    }

    public function testRejectsAnInvalidLiteralCategory(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT NULL');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $literal = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $literal);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new BranchIdentifier($literal);
    }
}
