<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\View\ViewDefinition;

#[CoversClass(ViewDefinition::class)]
#[Medium]
final class ViewDefinitionTest extends TestCase
{
    public function testValueRejectsAResultProcessorInTheViewBody(): void
    {
        $this->expectExceptionMessage('A view query cannot invoke PROCEDURE ANALYSE.');

        new ViewDefinition(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('v')), new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))], procedure: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse()));
    }

    public function testRenderHeadWritesAlgorithmDefinerAndSecurity(): void
    {
        self::assertSame('CREATE ALGORITHM = TEMPTABLE DEFINER = u@`%` SQL SECURITY DEFINER VIEW v AS SELECT 1 AS a', (new Semantics(Dialect::MySql))->analyze('CREATE ALGORITHM = TEMPTABLE DEFINER = \'u\'@\'%\' SQL SECURITY DEFINER VIEW v AS SELECT 1 AS a')->toString());
    }

    public function testRenderWritesNameColumnsQueryAndCheckOption(): void
    {
        self::assertSame('CREATE VIEW db.v (a, b) AS SELECT 1, 2 WITH LOCAL CHECK OPTION', (new Semantics(Dialect::MySql))->analyze('CREATE VIEW db.v (a, b) AS SELECT 1, 2 WITH LOCAL CHECK OPTION')->toString());
    }
}
