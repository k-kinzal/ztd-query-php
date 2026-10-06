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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SearchedCase;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(SearchedCase::class)]
#[Medium]
final class SearchedCaseTest extends TestCase
{
    public function testDeriveProgramDerivesEveryBranch(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(a INT) CASE WHEN b THEN SELECT d; WHEN a THEN SELECT a; ELSE SELECT e; END CASE');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $case = $create->body;
        self::assertInstanceOf(SearchedCase::class, $case);

        self::assertCount(2, $case->branches);
        self::assertCount(1, $case->otherwise);
        self::assertSame(['Column b does not exist.', 'Column d does not exist.', 'Column e does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testDeriveProgramChecksTheLabelsInTheBranches(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(a INT) l: LOOP CASE WHEN a > 0 THEN LEAVE l; ELSE LEAVE m; END CASE; END LOOP');

        self::assertSame(['LEAVE with no matching label: m'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
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
        yield 'without ELSE in 5.6' => ['mysql-5.6.51', 'create procedure p(a int) case when a > 1 then select 1; when a < 0 then select 2; end case', 'CREATE PROCEDURE p(a INT) CASE WHEN a > 1 THEN SELECT 1; WHEN a < 0 THEN SELECT 2; END CASE'];
        yield 'with ELSE in 5.7' => ['mysql-5.7.44', 'create procedure p(a int) case when a > 1 then select 1; else select 3; select 4; end case', 'CREATE PROCEDURE p(a INT) CASE WHEN a > 1 THEN SELECT 1; ELSE SELECT 3; SELECT 4; END CASE'];
        yield 'with an empty block in 9.1' => ['mysql-9.1.0', 'create procedure p(a int) case when a is null then begin end; end case', 'CREATE PROCEDURE p(a INT) CASE WHEN a IS NULL THEN BEGIN END; END CASE'];
    }

    public function testACaseWithoutBranchesIsRejected(): void
    {
        $this->expectExceptionMessage('CASE holds at least one WHEN branch.');

        new SearchedCase([]);
    }
}
