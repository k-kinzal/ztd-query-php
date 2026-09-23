<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\PasswordOperands;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PasswordOperands::class)]
#[Medium]
final class PasswordOperandsTest extends TestCase
{
    public function testValidateRejectsNumericCredentials(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SET PASSWORD = 'new'");
        $select = $binder->bind('SELECT 42');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $select);
        $number = $select->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $number);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        PasswordOperands::validate($statement->origin, $number);
    }

    public function testValidateRejectsAnotherDialectLiteral(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET PASSWORD = 'new'")->origin;
        $select = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind("SELECT 'new'");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $select);
        $text = $select->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $text);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        PasswordOperands::validate($origin, $text);
    }
}
