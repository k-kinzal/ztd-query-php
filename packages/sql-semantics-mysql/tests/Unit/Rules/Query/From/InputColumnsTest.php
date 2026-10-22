<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\From\InputColumns;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(InputColumns::class)]
#[Small]
final class InputColumnsTest extends TestCase
{
    public function testOfChecksTheReadRoleOfADerivedValuesTable(): void
    {
        $row = new M\Query\ValueRow([]);
        $table = new M\Relation\DerivedTable(new M\Query\ValuesQuery([$row]), new Name('q'));
        $problem = (new InputColumns())->of($table);
        self::assertInstanceOf(M\Query\Problem\Misuse::class, $problem);
        self::assertSame($row, $problem->valueRow);
    }

    public function testInputsActivatesOnlyCommonTablesThatTheWriteReads(): void
    {
        $row = new M\Query\ValueRow([]);
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], new M\Query\ValuesQuery([$row]))]);
        $rules = new InputColumns();
        self::assertNull($rules->inputs([new M\Relation\TableReference(new QualifiedName(new Name('t')))], $with));
        $problem = $rules->inputs([new M\Relation\TableReference(new QualifiedName(new Name('c')))], $with);
        self::assertInstanceOf(M\Query\Problem\Misuse::class, $problem);
        self::assertSame($row, $problem->valueRow);
    }

    public function testInputsChecksScalarWriteOperandsInTheirWithScope(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1')), new M\Query\SelectExpression(new M\Literal\NumberLiteral('2'))]);
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], $query)]);
        $value = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c')))));
        $rules = new InputColumns();
        self::assertNull($rules->inputs([$value]));
        self::assertInstanceOf(M\Expression\Problem\OperandColumns::class, $rules->inputs([$value], $with));
        $target = new M\Dml\WriteTarget(new QualifiedName(new Name('t')));
        $write = new M\Routine\Program\Write\DeferredDelete($with, [], $target, $value);
        self::assertInstanceOf(M\Expression\Problem\OperandColumns::class, $rules->of($write));
        $this->expectExceptionMessage('An ordinary DELETE has coherent input and operand columns.');
        new M\Dml\Delete($with, [], $target, $value);
    }
}
