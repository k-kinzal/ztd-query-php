<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\LabelFacts;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(LabelFacts::class)]
#[Medium]
final class LabelFactsTest extends TestCase
{
    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerEnterReportsALabelAnEnclosingStatementUses')]
    public function testEnterReportsALabelAnEnclosingStatementUses(string $release, string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerEnterReportsALabelAnEnclosingStatementUses(): iterable
    {
        yield 'a block in a block in 5.6' => ['mysql-5.6.51', 'CREATE PROCEDURE p() a: BEGIN a: BEGIN END; END', ['Redefining label a']];
        yield 'a loop in a block in another case' => ['mysql-8.0.44', 'CREATE PROCEDURE p() b: BEGIN B: LOOP LEAVE b; END LOOP; END', ['Redefining label B']];
        yield 'a loop in a loop' => ['mysql-9.1.0', 'CREATE PROCEDURE p() l: LOOP l: REPEAT LEAVE l; UNTIL 1 END REPEAT; END LOOP', ['Redefining label l']];
        yield 'sibling blocks of one label' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN b: BEGIN END; b: BEGIN END; END', []];
        yield 'a label of a handler block' => ['mysql-5.7.44', 'CREATE PROCEDURE p() l: BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION l: BEGIN END; END', []];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerEnterReportsAnEndLabelWithoutMatch')]
    public function testEnterReportsAnEndLabelWithoutMatch(string $release, string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerEnterReportsAnEndLabelWithoutMatch(): iterable
    {
        yield 'a block in 5.6' => ['mysql-5.6.51', 'CREATE PROCEDURE p() a: BEGIN b: BEGIN END a; END', ['End-label a without match']];
        yield 'a LOOP in 8.0' => ['mysql-8.0.44', 'CREATE PROCEDURE p() l: LOOP LEAVE l; END LOOP m', ['End-label m without match']];
        yield 'a WHILE in 9.1' => ['mysql-9.1.0', 'CREATE PROCEDURE p() w: WHILE 0 DO SELECT 1; END WHILE v', ['End-label v without match']];
        yield 'a REPEAT in 9.1' => ['mysql-9.1.0', 'CREATE PROCEDURE p() r: REPEAT SELECT 1; UNTIL 1 END REPEAT s', ['End-label s without match']];
        yield 'an end label in another case' => ['mysql-9.1.0', 'CREATE PROCEDURE p() b: BEGIN w: WHILE 0 DO SELECT 1; END WHILE W; END B', []];
        yield 'a label without end label' => ['mysql-5.7.44', 'CREATE PROCEDURE p() b: BEGIN END', []];
    }
}
