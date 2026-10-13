<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ViewQueries;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(ViewQueries::class)]
#[Small]
final class ViewQueriesTest extends TestCase
{
    public function testCheckRejectsAProcessorInsideQueryWrappers(): void
    {
        $query = new ParenthesizedQuery(new Select([], [new SelectExpression(new NumberLiteral('1'))], procedure: new ProcedureAnalyse()));
        $this->expectExceptionMessage('A view query cannot invoke PROCEDURE ANALYSE.');

        (new ViewQueries())->check($query);
    }

    public function testCheckAcceptsAPlainQuery(): void
    {
        (new ViewQueries())->check(new Select([], [new SelectExpression(new NumberLiteral('1'))]));

        $this->addToAssertionCount(1);
    }
}
