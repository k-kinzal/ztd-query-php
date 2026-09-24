<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Control\RaiseError;
use SqlSemantics\Model\Scalar\Control\RaiseIgnore;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\ControlExpressions;

#[CoversClass(ControlExpressions::class)]
#[Medium]
final class ControlExpressionsTest extends TestCase
{
    public function testWriteSerializesEveryRaiseFormInsideATrigger(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind("CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(ABORT, 'no'); SELECT RAISE(IGNORE); SELECT RAISE(FAIL, 'f'); SELECT RAISE(ROLLBACK, 'r'); END");
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $expected = 'CREATE TRIGGER "tr" BEFORE INSERT ON "main"."t" FOR EACH ROW BEGIN SELECT RAISE(ABORT, \'no\'); SELECT RAISE(IGNORE); SELECT RAISE(FAIL, \'f\'); SELECT RAISE(ROLLBACK, \'r\'); END';
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testWriteSpellsTheActionAndMessageOfAnError(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INT)')))->bind("CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(ABORT, 'no'); END");
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(RaiseError::class, $raise);
        self::assertSame('ABORT', $raise->action->value);
        self::assertSame("'no'", $raise->message->spelling());
        self::assertSame("RAISE(ABORT, 'no')", ControlExpressions::write($raise)->toString());
    }

    public function testWriteSpellsAnIgnoreWithoutAMessage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INT)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(IGNORE); END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(RaiseIgnore::class, $raise);
        self::assertSame('RAISE(IGNORE)', ControlExpressions::write($raise)->toString());
    }
}
