<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\CaseBranch;
use SqlSemantics\Platform\Sqlite\Statement\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Operation;

#[CoversClass(CaseBranch::class)]
#[Medium]
final class CaseBranchTest extends TestCase
{
    public function testRenderKeepsTheConditionAndTheResultInOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('select case when a > 1 then \'x\' when a then 2 end AS c1 from t');
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(CaseExpression::class, $statement->columns[0]->expression);
        $branch = $statement->columns[0]->expression->branches[1];
        self::assertInstanceOf(ColumnUse::class, $branch->when);
        self::assertInstanceOf(IntegerLiteral::class, $branch->then);
        self::assertSame('a', $branch->when->name->value);
        self::assertSame('2', $branch->then->digits);
        self::assertSame('SELECT CASE WHEN a > 1 THEN \'x\' WHEN a THEN 2 END AS c1 FROM t', $operation->toString());
    }

    public function testRenderWritesANewlyBuiltBranch(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $branch = new CaseBranch(new IntegerLiteral('1'), new TextLiteral('one'));
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new CaseExpression(null, [$branch, new CaseBranch(new IntegerLiteral('2'), new NullLiteral())]))]));

        self::assertSame("SELECT CASE WHEN 1 THEN 'one' WHEN 2 THEN NULL END", $operation->toString());
    }
}
