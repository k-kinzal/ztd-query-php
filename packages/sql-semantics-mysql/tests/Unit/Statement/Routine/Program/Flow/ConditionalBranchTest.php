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
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;

#[CoversClass(ConditionalBranch::class)]
#[Medium]
final class ConditionalBranchTest extends TestCase
{
    public function testRenderWritesTheConditionAndTheStatements(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(a INT) IF a = 1 THEN SELECT 1; ELSEIF a = 2 THEN SELECT 2; SELECT 3; END IF');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $if = $create->body;
        self::assertInstanceOf(IfStatement::class, $if);

        self::assertCount(1, $if->branches[0]->statements);
        self::assertCount(2, $if->branches[1]->statements);
        self::assertSame('CREATE PROCEDURE p(a INT) IF a = 1 THEN SELECT 1; ELSEIF a = 2 THEN SELECT 2; SELECT 3; END IF', $operation->toString());
    }

    #[DataProvider('providerRenderWritesTheBranch')]
    public function testRenderWritesTheBranch(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheBranch(): iterable
    {
        yield 'a compared value in 5.6' => ['mysql-5.6.51', 'create procedure p(a int) case a when 1 + 1 then select 1; end case', 'CREATE PROCEDURE p(a INT) CASE a WHEN 1 + 1 THEN SELECT 1; END CASE'];
        yield 'a search condition in 8.4' => ['mysql-8.4.7', 'create procedure p(a int) case when a between 1 and 2 then select 1; select 2; end case', 'CREATE PROCEDURE p(a INT) CASE WHEN a BETWEEN 1 AND 2 THEN SELECT 1; SELECT 2; END CASE'];
        yield 'a program statement in 9.1' => ['mysql-9.1.0', 'create procedure p(a int) l: loop if a then leave l; end if; end loop', 'CREATE PROCEDURE p(a INT) l: LOOP IF a THEN LEAVE l; END IF; END LOOP'];
    }

    public function testABranchWithoutStatementsIsRejected(): void
    {
        $this->expectExceptionMessage('A statement list holds statements.');

        new ConditionalBranch(new NumberLiteral('1'), []);
    }
}
