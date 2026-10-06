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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Leave;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\WhileLoop;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(WhileLoop::class)]
#[Medium]
final class WhileLoopTest extends TestCase
{
    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramDerivesTheLabelsTheConditionAndTheStatements')]
    public function testDeriveProgramDerivesTheLabelsTheConditionAndTheStatements(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramDerivesTheLabelsTheConditionAndTheStatements(): iterable
    {
        yield 'a variable in the condition' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 3; w: WHILE x > 0 DO SET x = x - 1; ITERATE w; END WHILE w; END', []];
        yield 'an unknown name in the condition' => ['CREATE PROCEDURE p(a INT) WHILE b > 0 DO SELECT 1; END WHILE', ['Column b does not exist.']];
        yield 'an unknown name in the statements' => ['CREATE PROCEDURE p(a INT) WHILE a DO SELECT z; END WHILE', ['Column z does not exist.']];
        yield 'an end label without match' => ['CREATE PROCEDURE p(a INT) w: WHILE a > 0 DO LEAVE w; END WHILE v', ['End-label v without match']];
        yield 'a redefined label' => ['CREATE PROCEDURE p(a INT) w: BEGIN w: WHILE a DO LEAVE w; END WHILE; END', ['Redefining label w']];
    }

    #[DataProvider('providerRenderWritesTheLoop')]
    public function testRenderWritesTheLoop(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheLoop(): iterable
    {
        yield 'without label in 5.6' => ['mysql-5.6.51', 'create procedure p(a int) while a do select 1; end while', 'CREATE PROCEDURE p(a INT) WHILE a DO SELECT 1; END WHILE'];
        yield 'with both labels in 8.0' => ['mysql-8.0.44', 'create procedure p(a int) again: while a > 0 do select 1; select 2; end while again', 'CREATE PROCEDURE p(a INT) again: WHILE a > 0 DO SELECT 1; SELECT 2; END WHILE again'];
        yield 'with a label in 9.1' => ['mysql-9.1.0', 'create procedure p(a int) again: while a is not null do leave again; end while', 'CREATE PROCEDURE p(a INT) again: WHILE a IS NOT NULL DO LEAVE again; END WHILE'];
    }

    public function testALoopWithoutStatementsIsRejected(): void
    {
        $this->expectExceptionMessage('A statement list holds statements.');

        new WhileLoop(new NumberLiteral('1'), []);
    }

    public function testAnEndLabelWithoutALabelIsRejected(): void
    {
        $this->expectExceptionMessage('Only a labeled loop has an end label.');

        new WhileLoop(new NumberLiteral('1'), [new Leave(new Name('w'))], null, new Name('w'));
    }
}
