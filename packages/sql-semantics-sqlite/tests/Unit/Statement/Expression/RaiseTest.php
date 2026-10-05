<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Raise;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RaiseAction;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Raise::class)]
#[Medium]
final class RaiseTest extends TestCase
{
    public function testDeriveScalarYieldsNoValueInsideATrigger(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze("CREATE TRIGGER r BEFORE DELETE ON t BEGIN SELECT RAISE(FAIL, 'no'); END", [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTrigger::class, $statement);
        self::assertInstanceOf(Select::class, $statement->steps[0]);
        self::assertInstanceOf(ResultColumn::class, $statement->steps[0]->columns[0]);
        self::assertInstanceOf(Raise::class, $statement->steps[0]->columns[0]->expression);
        $raise = $statement->steps[0]->columns[0]->expression;
        self::assertSame(RaiseAction::Fail, $raise->action);
        self::assertInstanceOf(TextLiteral::class, $raise->message);
        self::assertSame('no', $raise->message->value);
        $fact = $operation->facts->scalar($raise);
        self::assertInstanceOf(NullOnly::class, $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
        self::assertTrue($operation->facts->covers($raise->message));
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarOutsideATriggerIsReportedByTheStatement(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT RAISE(IGNORE)', []);

        self::assertEquals([new Misuse(MisuseRule::RaiseOutsideTrigger)], $operation->facts->diagnostics);
        self::assertInstanceOf(NullOnly::class, $operation->field(0)->type);
    }

    public function testRenderWritesTheActionAndTheMessage(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create trigger r before delete on t begin select raise(ignore) AS c1; select raise(rollback, \'r\') AS c2, raise(abort, \'a\') AS c3, raise(fail, \'f\') AS c4; end');

        self::assertSame('CREATE TRIGGER r BEFORE DELETE ON t BEGIN SELECT RAISE(IGNORE) AS c1; SELECT RAISE(ROLLBACK, \'r\') AS c2, RAISE(ABORT, \'a\') AS c3, RAISE(FAIL, \'f\') AS c4; END', $operation->toString());
    }

    public function testRenderWritesANewlyBuiltRaise(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new Raise(RaiseAction::Ignore)), new ResultColumn(new Raise(RaiseAction::Abort, new TextLiteral('stop')))]));

        self::assertSame("SELECT RAISE(IGNORE), RAISE(ABORT, 'stop')", $operation->toString());
    }
}
