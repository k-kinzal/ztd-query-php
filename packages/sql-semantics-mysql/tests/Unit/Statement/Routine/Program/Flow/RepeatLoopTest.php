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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\RepeatLoop;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RepeatLoop::class)]
#[Medium]
final class RepeatLoopTest extends TestCase
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
        yield 'a variable in the condition' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 0; r: REPEAT SET x = x + 1; ITERATE r; UNTIL x > 3 END REPEAT r; END', []];
        yield 'an unknown name in the condition' => ['CREATE PROCEDURE p(a INT) REPEAT SELECT 1; UNTIL b END REPEAT', ['Column b does not exist.']];
        yield 'an unknown name in the statements' => ['CREATE PROCEDURE p(a INT) REPEAT SELECT z; UNTIL a END REPEAT', ['Column z does not exist.']];
        yield 'an end label without match' => ['CREATE PROCEDURE p(a INT) r: REPEAT SELECT 1; UNTIL a END REPEAT s', ['End-label s without match']];
        yield 'a redefined label' => ['CREATE PROCEDURE p(a INT) r: LOOP r: REPEAT LEAVE r; UNTIL a END REPEAT; END LOOP', ['Redefining label r']];
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
        yield 'without label in 5.6' => ['mysql-5.6.51', 'create procedure p(a int) repeat select 1; until a end repeat', 'CREATE PROCEDURE p(a INT) REPEAT SELECT 1; UNTIL a END REPEAT'];
        yield 'with both labels in 5.7' => ['mysql-5.7.44', 'create procedure p(a int) again: repeat select 1; select 2; until a > 0 end repeat again', 'CREATE PROCEDURE p(a INT) again: REPEAT SELECT 1; SELECT 2; UNTIL a > 0 END REPEAT again'];
        yield 'with a label in 9.1' => ['mysql-9.1.0', 'create procedure p(a int) again: repeat leave again; until a in (1, 2) end repeat', 'CREATE PROCEDURE p(a INT) again: REPEAT LEAVE again; UNTIL a IN (1, 2) END REPEAT'];
    }

    public function testALoopWithoutStatementsIsRejected(): void
    {
        $this->expectExceptionMessage('A statement list holds statements.');

        new RepeatLoop(new NumberLiteral('1'), []);
    }

    public function testAnEndLabelWithoutALabelIsRejected(): void
    {
        $this->expectExceptionMessage('Only a labeled loop has an end label.');

        new RepeatLoop(new NumberLiteral('1'), [new Leave(new Name('r'))], null, new Name('r'));
    }
}
