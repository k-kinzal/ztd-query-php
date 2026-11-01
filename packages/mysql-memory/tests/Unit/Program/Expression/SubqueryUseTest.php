<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Program\Expression\SubqueryUse::class)]
#[Medium]
final class SubqueryUseTest extends TestCase
{
    public function testRaiseKeepsTheModernScalarQuantifierWidthAheadOfTheOuterName(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7'))->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1241);
        $session->query('SELECT missing = ALL(SELECT 1,2)');
    }

    public function testWidthChecksTheBoundOuterProjectionBeforeNestedFields(): void
    {
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('4')])]);
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        $predicate = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate($row, $query);
        $use = new \MySqlMemory\Program\Expression\SubqueryUse();
        $use->width($predicate, 2);
        $problem = $predicate->shape();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem);
        self::assertSame(3, $problem->expected);
        $this->expectExceptionMessage('Operand should contain 2 column(s)');
        $use->width($predicate, 1);
    }

    public function testOperandUsesTheMembershipDiagnosticClause(): void
    {
        $session = (new \MySqlMemory\Instance('8.0.44'))->connect();
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionMessage("Unknown column 'missing' in 'IN/ALL/ANY subquery'");
        $session->query('SELECT missing = ALL(SELECT 1,2)');
    }
}
