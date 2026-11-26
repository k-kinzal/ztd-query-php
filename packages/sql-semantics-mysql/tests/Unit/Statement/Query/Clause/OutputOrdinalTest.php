<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(OutputOrdinal::class)]
#[Medium]
final class OutputOrdinalTest extends TestCase
{
    public function testPositionAnswersTheWrittenPosition(): void
    {
        self::assertSame(2, (new OutputOrdinal(new NumberLiteral('2')))->position());
        self::assertSame(3, (new OutputOrdinal(new NumberLiteral('003')))->position());
        self::assertSame(PHP_INT_MAX, (new OutputOrdinal(new NumberLiteral('9999999999999999999')))->position());
    }

    public function testDeriveScalarTakesTheFactsOfTheField(): void
    {
        $semantics = new Inspection();
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::Nullable),
        ]);
        $operation = $semantics->diagnose('SELECT 1, a FROM t GROUP BY 2 ORDER BY 2', [$table]);

        self::assertInstanceOf(Select::class, $operation->statement);
        $fact = $operation->facts->scalar($operation->statement->orderBy[0]->expression);
        self::assertInstanceOf(AliasTarget::class, $fact->resolution);
        self::assertSame($operation->field(1), $fact->resolution->field);
        self::assertSame(Nullability::Nullable, $fact->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarReportsAPositionOutsideTheSelectList(): void
    {
        $operation = (new Inspection())->diagnose('SELECT 1 ORDER BY 3');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(OrdinalOutOfRange::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveScalarDependsOnAnOpenStar(): void
    {
        $operation = (new Inspection())->diagnose('SELECT * FROM t ORDER BY 2');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Dependent::class, $operation->facts->scalar($operation->statement->orderBy[0]->expression)->type);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheInteger(): void
    {
        self::assertSame('SELECT a FROM t ORDER BY 1 DESC', (new Inspection())->diagnose('select a from t order by 1 desc')->toString());
    }

    public function testADecimalIsRejected(): void
    {
        $this->expectExceptionMessage('A select list position is an unsigned integer.');

        new OutputOrdinal(new NumberLiteral('1.5'));
    }
}
