<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\WindowReferences;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(WindowReferences::class)]
#[Medium]
final class WindowReferencesTest extends TestCase
{
    public function testCheckReportsAWindowNameTheBlockDoesNotDefine(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT ROW_NUMBER() OVER w, SUM(1) OVER (v ORDER BY 1.5) FROM DUAL WINDOW v AS (u)', []);

        self::assertCount(2, $operation->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(MisuseRule::UnknownWindow, $operation->facts->diagnostics[0]->rule);
    }

    public function testCheckAcceptsANameWithoutRegardToLetterCaseAndLeavesSubqueriesAlone(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT ROW_NUMBER() OVER W, (SELECT RANK() OVER w FROM DUAL WINDOW w AS ()) FROM DUAL WINDOW w AS ()', []);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testNamesAnswersTheWindowNamesInWrittenOrder(): void
    {
        $select = (new Semantics(Dialect::MySql))->analyze('SELECT ROW_NUMBER() OVER a, LAG(1) OVER (b), (SELECT RANK() OVER c) FROM DUAL WINDOW d AS (e)')->statement;
        self::assertInstanceOf(Select::class, $select);

        self::assertSame(['a', 'b', 'e'], array_map(static fn (Name $name): string => $name->value, (new WindowReferences())->names([...$select->items, ...$select->windows])));
    }
}
