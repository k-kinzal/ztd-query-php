<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(IfStatement::class)]
#[Medium]
final class IfStatementTest extends TestCase
{
    public function testDeriveProgramDerivesEveryBranch(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(a INT) IF b THEN SELECT c; ELSEIF a THEN SELECT d; ELSE SELECT e; END IF');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $if = $create->body;
        self::assertInstanceOf(IfStatement::class, $if);

        self::assertCount(2, $if->branches);
        self::assertCount(1, $if->otherwise);
        self::assertSame(['Column b does not exist.', 'Column c does not exist.', 'Column d does not exist.', 'Column e does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testDeriveProgramResolvesTheVariablesInScope(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 1; l: LOOP IF x > 1 THEN LEAVE l; ELSE SET x = x + 1; ITERATE l; END IF; END LOOP; END');

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
        yield 'IF, ELSEIF and ELSE in 5.6' => ['mysql-5.6.51', 'create procedure p(a int) if a = 1 then select 1; elseif a = 2 then select 2; select 3; else select 4; end if', 'CREATE PROCEDURE p(a INT) IF a = 1 THEN SELECT 1; ELSEIF a = 2 THEN SELECT 2; SELECT 3; ELSE SELECT 4; END IF'];
        yield 'IF alone in 8.0' => ['mysql-8.0.44', 'create procedure p(a int) if a then select 1; end if', 'CREATE PROCEDURE p(a INT) IF a THEN SELECT 1; END IF'];
        yield 'nested IF in 9.1' => ['mysql-9.1.0', 'create procedure p(a int) if a then if not a then begin end; end if; end if', 'CREATE PROCEDURE p(a INT) IF a THEN IF NOT a THEN BEGIN END; END IF; END IF'];
    }

    public function testAnIfWithoutBranchesIsRejected(): void
    {
        $this->expectExceptionMessage('IF holds at least one branch.');

        new IfStatement([]);
    }
}
