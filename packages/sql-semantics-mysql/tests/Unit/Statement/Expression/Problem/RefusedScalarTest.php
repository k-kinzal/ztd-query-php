<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(M\Expression\Problem\RefusedScalar::class)]
#[Medium]
final class RefusedScalarTest extends TestCase
{
    public function testMessageRetainsTheOriginalOperand(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $problem = new M\Expression\Problem\RefusedScalar($row);

        self::assertSame($row, $problem->operand);
    }

    public function testMessageReportsTheRetainedOperandConstraint(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        self::assertSame('Operand should contain 1 column(s), not 2.', (new M\Expression\Problem\RefusedScalar($row))->message());
    }

    public function testLoweringPreservesDependenciesOfARejectedScalarUse(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('SET @x=(VALUES ROW() UNION SELECT @@autocommit)');
            self::fail('An ordinary assignment cannot retain a zero-column scalar.');
        } catch (RejectedInput $error) {
            self::assertInstanceOf(M\Expression\Problem\RefusedScalar::class, $error->problem);
            self::assertInstanceOf(M\Routine\Program\Expression\DeferredSubquery::class, $error->problem->operand);
            self::assertInstanceOf(M\Query\Set\SetOperation::class, $error->problem->operand->query);
            self::assertInstanceOf(M\Query\Select::class, $error->problem->operand->query->right);
        }
    }
}
