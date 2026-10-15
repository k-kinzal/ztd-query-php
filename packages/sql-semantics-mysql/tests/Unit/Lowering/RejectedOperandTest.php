<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\RejectedOperand;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange;

#[CoversClass(RejectedOperand::class)]
#[Medium]
final class RejectedOperandTest extends TestCase
{
    public function testOfRefusesNestedPartitioningAfterEarlierProgramDeclarations(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $sql = 'CREATE PROCEDURE d.p() BEGIN DECLARE x INT; DECLARE x INT; PARTITION BY HASH(a); END';
        $failure = (new SyntaxWarnings())->rejected($semantics->parser()->parse($sql), $semantics->profile());

        self::assertInstanceOf(RejectedInput::class, $failure);
        self::assertSame('Duplicate variable: x', $failure->problem->message());
        self::assertLessThan(strpos($sql, 'PARTITION'), $failure->end);
    }

    public function testLoweredKeepsTheFactorBoundaryBeforeItsAuthenticationClause(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $sql = 'ALTER USER u ADD 01 FACTOR IDENTIFIED WITH p ADD 2 FACTOR IDENTIFIED WITH q';
        $failure = (new SyntaxWarnings())->rejected($semantics->parser()->parse($sql), $semantics->profile());

        self::assertInstanceOf(RejectedInput::class, $failure);
        self::assertEquals(new NumberOutOfRange('nth factor', '01', 'ER_WRONG_VALUE'), $failure->problem);
        self::assertSame(strlen('ALTER USER u ADD 01 FACTOR'), $failure->end);
        self::assertTrue($failure->aborts);
    }
}
