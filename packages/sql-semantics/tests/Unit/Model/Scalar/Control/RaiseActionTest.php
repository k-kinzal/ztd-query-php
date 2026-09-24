<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Control;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Control\RaiseAction;
use SqlSemantics\Model\Scalar\Control\RaiseError;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RaiseAction::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class RaiseActionTest extends TestCase
{
    #[TestWith(['ROLLBACK', RaiseAction::Rollback])]
    #[TestWith(['ABORT', RaiseAction::Abort])]
    #[TestWith(['FAIL', RaiseAction::Fail])]
    public function testBindsEachTriggerEffectKeyword(string $keyword, RaiseAction $action): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(' . $keyword . ", 'stop'); END");
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(RaiseError::class, $raise);
        self::assertSame($action, $raise->action);
        self::assertSame($keyword, $action->value);
    }

    public function testSpellsEveryEffectAsItsSqliteKeyword(): void
    {
        self::assertSame(['ROLLBACK', 'ABORT', 'FAIL'], array_map(static fn (RaiseAction $action): string => $action->value, RaiseAction::cases()));
    }
}
