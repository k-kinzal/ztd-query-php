<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\CursorFacts;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(CursorFacts::class)]
#[Medium]
final class CursorFactsTest extends TestCase
{
    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerCursorReportsACursorDeclaredNowhereAround')]
    public function testCursorReportsACursorDeclaredNowhereAround(string $release, string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerCursorReportsACursorDeclaredNowhereAround(): iterable
    {
        yield 'OPEN without a declaration in 5.6' => ['mysql-5.6.51', 'CREATE PROCEDURE p() OPEN c', ['Undefined CURSOR: c']];
        yield 'CLOSE of another cursor in 8.0' => ['mysql-8.0.44', 'CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; CLOSE d; END', ['Undefined CURSOR: d']];
        yield 'FETCH of another cursor in 9.1' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; FETCH d INTO x; END', ['Undefined CURSOR: d']];
        yield 'a cursor of an inner block' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN BEGIN DECLARE c CURSOR FOR SELECT 1; END; OPEN c; END', ['Undefined CURSOR: c']];
        yield 'a cursor declared after the handler' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLWARNING OPEN c; DECLARE c CURSOR FOR SELECT 1; END', ['Undefined CURSOR: c', 'Cursor declaration after handler declaration']];
        yield 'a cursor of an outer block' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; BEGIN OPEN c; CLOSE c; END; END', []];
        yield 'a cursor in another case' => ['mysql-5.7.44', 'CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE Cur CURSOR FOR SELECT 1; OPEN CUR; FETCH cur INTO x; CLOSE cUR; END', []];
        yield 'a cursor in a handler' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE CONTINUE HANDLER FOR NOT FOUND CLOSE c; END', []];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerVariablesReportsEveryTargetThatIsNoVariable')]
    public function testVariablesReportsEveryTargetThatIsNoVariable(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerVariablesReportsEveryTargetThatIsNoVariable(): iterable
    {
        yield 'two undeclared targets' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1, 2, 3; FETCH NEXT FROM c INTO y, x, z; END', ['Undeclared variable: y', 'Undeclared variable: z']];
        yield 'a variable of a closed block' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; BEGIN DECLARE x INT; END; FETCH c INTO x; END', ['Undeclared variable: x']];
        yield 'a parameter and an outer variable' => ['CREATE PROCEDURE p(OUT a INT) BEGIN DECLARE b INT; DECLARE c CURSOR FOR SELECT 1, 2; BEGIN FETCH c INTO A, b; END; END', []];
        yield 'targets in a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; DECLARE CONTINUE HANDLER FOR NOT FOUND FETCH c INTO x, y; END', ['Undeclared variable: y']];
    }

    public function testVariablesReportsATargetOfGetDiagnosticsThatIsNoVariable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(n INT) GET DIAGNOSTICS n = NUMBER, r = ROW_COUNT');

        self::assertSame(['Undeclared variable: r'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }
}
