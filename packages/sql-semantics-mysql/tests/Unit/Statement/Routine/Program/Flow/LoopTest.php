<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Leave;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Loop::class)]
#[Medium]
final class LoopTest extends TestCase
{
    public function testDeriveProgramAddsTheLabelOfTheLoop(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 1; l: LOOP SET x = x + 1; LEAVE l; END LOOP l; END');

        self::assertSame([], $operation->facts->diagnostics);
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramReportsTheBrokenRule')]
    public function testDeriveProgramReportsTheBrokenRule(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramReportsTheBrokenRule(): iterable
    {
        yield 'a redefined label' => ['CREATE PROCEDURE p() l: LOOP l: LOOP LEAVE l; END LOOP; END LOOP', ['Redefining label l']];
        yield 'an end label without match' => ['CREATE PROCEDURE p() l: LOOP LEAVE l; END LOOP m', ['End-label m without match']];
        yield 'an unknown name in the statements' => ['CREATE PROCEDURE p() LOOP SELECT z; END LOOP', ['Column z does not exist.']];
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
        yield 'without label in 5.6' => ['mysql-5.6.51', 'create procedure p() loop select 1; select 2; end loop', 'CREATE PROCEDURE p() LOOP SELECT 1; SELECT 2; END LOOP'];
        yield 'with both labels in 5.7' => ['mysql-5.7.44', 'create procedure p() again: loop leave again; end loop again', 'CREATE PROCEDURE p() again: LOOP LEAVE again; END LOOP again'];
        yield 'with a label in 9.1' => ['mysql-9.1.0', 'create procedure p() again: loop leave again; end loop', 'CREATE PROCEDURE p() again: LOOP LEAVE again; END LOOP'];
    }

    public function testALoopWithoutStatementsIsRejected(): void
    {
        $this->expectExceptionMessage('A statement list holds statements.');

        new Loop([]);
    }

    public function testAnEndLabelWithoutALabelIsRejected(): void
    {
        $this->expectExceptionMessage('Only a labeled loop has an end label.');

        new Loop([new Leave(new Name('l'))], null, new Name('l'));
    }
}
