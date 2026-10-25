<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Selections;
use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\SelectionUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Selections::class)]
#[Medium]
final class SelectionsTest extends TestCase
{
    public function testBoundKeepsTheDefiningCommonTableScope(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        $session->query('WITH b AS (SELECT 7 AS a), c AS (SELECT ROW(a,2) FROM b) SELECT 1 FROM c');
    }

    public function testClausesPrepareTheWhereDependencyBeforeTheHavingRow(): void
    {
        $session = (new Instance())->connect();
        $selection = new DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new NumberLiteral('1'))], where: new ColumnUse(new Name('missing')), having: new Row([new NumberLiteral('1'), new NumberLiteral('2')]));
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Unknown column 'missing' in 'where clause'");
        (new SelectionUse())->raise($selection, $session);
    }

    public function testOperandResolvesAnOutputAliasBeforeTheHavingRowCondition(): void
    {
        $session = (new Instance())->connect();
        $having = new Row([new ColumnUse(new Name('v')), new NumberLiteral('2')]);
        $selection = new DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new NumberLiteral('7'), new Name('v'))], having: $having);
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        (new SelectionUse())->raise($selection, $session);
    }    public function testSetPreparesItsOperandBeforeRequiringACombinedOutputType(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        $session->query('WITH x AS (SELECT ROW(1,2) REGEXP 3 UNION SELECT 4) SELECT * FROM x');
    }

    public function testValuesPreparesTheRetainedDependencyBeforeItsRowCondition(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $session->query('WITH c AS (VALUES ROW(CHAR(ROW(missing, 2)))) SELECT * FROM c');
    }

    public function testValuesRejectsDefaultBeforeTheFollowingRetainedRow(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValuesQuery([
            new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest()]),
            new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValueRow([new Row([new NumberLiteral('1'), new NumberLiteral('2')])]),
        ]);
        $context = $session->semantics()->context([], true);
        $derivation = (new \MySqlMemory\Session\Resolution\Parameters())->derivation($query, $context);
        $derivation->query($query, $derivation->environment());
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3943);
        (new \MySqlMemory\Program\Expression\QueryUse())->plan(new \MySqlMemory\Session\Resolution\QueryPreparation($query, $derivation->facts(), $context->profile), $session);
    }

    public function testBoundRejectsQualifyBeforeProjectionWidthWithoutReadingInputText(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $selection = new DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($row)], qualify: new Row([new NumberLiteral('3'), new NumberLiteral('4')]));
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(6037);
        (new SelectionUse())->raise($selection, $session);
    }

}
