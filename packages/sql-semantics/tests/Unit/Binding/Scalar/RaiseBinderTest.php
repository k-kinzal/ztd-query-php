<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\RaiseBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RaiseBinder::class)]
#[Medium]
final class RaiseBinderTest extends TestCase
{
    public function testBindClassifiesEachRaiseActionWithItsMessage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)')))->bind("CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(ABORT, 'no'); SELECT RAISE(IGNORE); SELECT RAISE(FAIL, 'r'); END");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement::class, $statement);
        [$abort, $ignore, $fail] = $statement->body->steps;
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $abort);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $ignore);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $fail);
        $expressions = [$abort->outputs[0]->expression, $ignore->outputs[0]->expression, $fail->outputs[0]->expression];
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Control\RaiseError::class, $expressions[0]);
        self::assertSame(\SqlSemantics\Model\Scalar\Control\RaiseAction::Abort, $expressions[0]->action);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $expressions[0]->message);
        self::assertSame("'no'", $expressions[0]->message->text);
        self::assertSame('never', $expressions[0]->type->name);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Control\RaiseIgnore::class, $expressions[1]);
        self::assertSame('never', $expressions[1]->type->name);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Control\RaiseError::class, $expressions[2]);
        self::assertSame(\SqlSemantics\Model\Scalar\Control\RaiseAction::Fail, $expressions[2]->action);
        self::assertSame("CREATE TRIGGER \"tr\" BEFORE INSERT ON \"main\".\"t\" FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'no'); SELECT RAISE(IGNORE); SELECT RAISE(FAIL, 'r'); END", $statement->toString());
    }

    public function testBindReadsRollbackAndKeepsTheResultNotNull(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)')))->bind("CREATE TRIGGER tr AFTER DELETE ON t BEGIN SELECT RAISE(ROLLBACK, 'gone'); END");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Control\RaiseError::class, $raise);
        self::assertSame(\SqlSemantics\Model\Scalar\Control\RaiseAction::Rollback, $raise->action);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $raise->nullability);
    }
}
