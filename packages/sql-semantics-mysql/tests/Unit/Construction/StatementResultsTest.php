<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Construction\StatementResults;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;

#[CoversClass(StatementResults::class)]
#[Medium]
final class StatementResultsTest extends TestCase
{
    public function testOfRetainsTheDerivedOutputAsStatementMeaning(): void
    {
        $construction = new Derivation((new Semantics(Dialect::MySql))->context());
        $statement = new QueryRoot(new Select([], [new SelectExpression(new NumberLiteral('12'))]));
        $construction->statement($statement);
        $result = (new StatementResults())->of($construction);
        self::assertSame($statement->query->queryInformation(), $result->output);
        self::assertSame($construction->context->profile, $result->profile);
    }

    public function testStatementWithNoRowsKeepsAnAbsentOutput(): void
    {
        $context = (new Semantics(Dialect::MySql))->context();
        $result = (new StatementResults())->statement($context, (new Derivation($context))->facts());
        self::assertNull($result->output);
        self::assertSame([], $result->declarations);
        self::assertSame([], $result->diagnostics);
        self::assertSame([], $result->warnings);
    }
}
