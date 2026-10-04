<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Raise;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RaiseAction;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;

#[CoversClass(RaiseAction::class)]
#[Medium]
final class RaiseActionTest extends TestCase
{
    public function testFromReadsEachKeyword(): void
    {
        self::assertSame(RaiseAction::Ignore, RaiseAction::from('IGNORE'));
        self::assertSame(RaiseAction::Rollback, RaiseAction::from('ROLLBACK'));
        self::assertSame(RaiseAction::Abort, RaiseAction::from('ABORT'));
        self::assertSame(RaiseAction::Fail, RaiseAction::from('FAIL'));
    }

    public function testCasesListTheFourActions(): void
    {
        self::assertSame(['IGNORE', 'ROLLBACK', 'ABORT', 'FAIL'], array_map(static fn (RaiseAction $action): string => $action->value, RaiseAction::cases()));
    }

    public function testFromMatchesTheActionOfAnAnalyzedRaise(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze("CREATE TRIGGER r BEFORE DELETE ON t BEGIN SELECT RAISE(abort, 'no'); END")->statement;

        self::assertInstanceOf(CreateTrigger::class, $statement);
        self::assertInstanceOf(Select::class, $statement->steps[0]);
        self::assertInstanceOf(ResultColumn::class, $statement->steps[0]->columns[0]);
        self::assertInstanceOf(Raise::class, $statement->steps[0]->columns[0]->expression);
        self::assertSame(RaiseAction::Abort, $statement->steps[0]->columns[0]->expression->action);
    }
}
