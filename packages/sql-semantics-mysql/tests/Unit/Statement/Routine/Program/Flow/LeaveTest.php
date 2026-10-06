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
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(Leave::class)]
#[Medium]
final class LeaveTest extends TestCase
{
    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramLooksTheLabelUp')]
    public function testDeriveProgramLooksTheLabelUp(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramLooksTheLabelUp(): iterable
    {
        yield 'the label of the loop' => ['CREATE PROCEDURE p() l: LOOP LEAVE l; END LOOP', []];
        yield 'the label of an outer block' => ['CREATE PROCEDURE p() b: BEGIN l: LOOP LEAVE b; END LOOP; END', []];
        yield 'a label in another case' => ['CREATE PROCEDURE p() L: LOOP LEAVE l; END LOOP l', []];
        yield 'no label' => ['CREATE PROCEDURE p() BEGIN LEAVE zz; END', ['LEAVE with no matching label: zz']];
        yield 'the label of a closed loop' => ['CREATE PROCEDURE p() BEGIN l: LOOP LEAVE l; END LOOP; LEAVE l; END', ['LEAVE with no matching label: l']];
        yield 'a label outside a handler' => ['CREATE PROCEDURE p() a: BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION LEAVE a; END a', ['LEAVE with no matching label: a']];
        yield 'the label of a handler block' => ['CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION h: BEGIN LEAVE h; END; END', []];
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
        yield '5.6' => ['mysql-5.6.51', 'create procedure p() l: loop leave l; end loop l', 'CREATE PROCEDURE p() l: LOOP LEAVE l; END LOOP l'];
        yield 'a quoted label in 8.4' => ['mysql-8.4.7', 'create procedure p() `my label`: begin leave `my label`; end', 'CREATE PROCEDURE p() `my label`: BEGIN LEAVE `my label`; END'];
    }
}
