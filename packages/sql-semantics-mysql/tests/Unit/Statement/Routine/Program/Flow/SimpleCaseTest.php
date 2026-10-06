<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(SimpleCase::class)]
#[Medium]
final class SimpleCaseTest extends TestCase
{
    public function testDeriveProgramDerivesTheOperandAndEveryBranch(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(a INT) CASE b WHEN c THEN SELECT d; WHEN 1 THEN SELECT a; ELSE SELECT e; END CASE');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $case = $create->body;
        self::assertInstanceOf(SimpleCase::class, $case);

        self::assertInstanceOf(ColumnUse::class, $case->operand);
        self::assertCount(2, $case->branches);
        self::assertCount(1, $case->otherwise);
        self::assertSame(['Column b does not exist.', 'Column c does not exist.', 'Column d does not exist.', 'Column e does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testDeriveProgramResolvesAVariableAsTheOperand(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 1; CASE x WHEN 1 THEN SET x = 2; ELSE BEGIN END; END CASE; END');

        self::assertSame([], $operation->facts->diagnostics);
    }

    #[DataProvider('providerRenderWritesTheStatement')]
    public function testRenderWritesTheStatement(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheStatement(): iterable
    {
        yield 'without ELSE in 5.6' => ['mysql-5.6.51', 'create procedure p(a int) case a when 1 then select 1; when 2 then select 2; end case', 'CREATE PROCEDURE p(a INT) CASE a WHEN 1 THEN SELECT 1; WHEN 2 THEN SELECT 2; END CASE'];
        yield 'with ELSE in 8.0' => ['mysql-8.0.44', 'create procedure p(a int) case a + 1 when 1 then select 1; else select 2; select 3; end case', 'CREATE PROCEDURE p(a INT) CASE a + 1 WHEN 1 THEN SELECT 1; ELSE SELECT 2; SELECT 3; END CASE'];
        yield 'a string operand in 9.1' => ['mysql-9.1.0', "create procedure p(a char(1)) case a when 'x' then begin end; end case", "CREATE PROCEDURE p(a CHAR(1)) CASE a WHEN 'x' THEN BEGIN END; END CASE"];
    }

    public function testACaseWithoutBranchesIsRejected(): void
    {
        $this->expectExceptionMessage('CASE holds at least one WHEN branch.');

        new SimpleCase(new NumberLiteral('1'), []);
    }
}
