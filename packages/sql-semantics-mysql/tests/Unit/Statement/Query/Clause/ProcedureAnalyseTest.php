<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ProcedureAnalyse::class)]
#[Medium]
final class ProcedureAnalyseTest extends TestCase
{
    public function testRenderWritesTheArguments(): void
    {
        self::assertSame('SELECT a FROM t PROCEDURE ANALYSE()', (new Inspection('mysql-5.6.51'))->inspect('select a from t procedure analyse ( )')->toString());
        self::assertSame('SELECT a FROM t PROCEDURE ANALYSE(10, 2000)', (new Inspection('mysql-5.7.44'))->inspect('select a from t procedure analyse(10,2000)')->toString());
    }

    public function testADecimalArgumentIsRejected(): void
    {
        $this->expectExceptionMessage('A PROCEDURE ANALYSE argument is an unsigned integer.');

        new ProcedureAnalyse([new NumberLiteral('1.5')]);
    }

    public function testAThirdArgumentIsRejected(): void
    {
        $this->expectExceptionMessage('PROCEDURE ANALYSE takes at most two arguments.');

        new ProcedureAnalyse([new NumberLiteral('1'), new NumberLiteral('2'), new NumberLiteral('3')]);
    }
}
