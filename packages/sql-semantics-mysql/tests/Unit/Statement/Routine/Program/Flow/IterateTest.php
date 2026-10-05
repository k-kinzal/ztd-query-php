<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Iterate;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(Iterate::class)]
#[Medium]
final class IterateTest extends TestCase
{
    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramRequiresAnEnclosingLoop')]
    public function testDeriveProgramRequiresAnEnclosingLoop(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramRequiresAnEnclosingLoop(): iterable
    {
        yield 'the label of a LOOP' => ['CREATE PROCEDURE p() again: LOOP ITERATE again; END LOOP', []];
        yield 'the label of a WHILE' => ['CREATE PROCEDURE p(a INT) w: WHILE a > 0 DO ITERATE w; END WHILE w', []];
        yield 'the label of a REPEAT' => ['CREATE PROCEDURE p(a INT) r: REPEAT ITERATE r; UNTIL a > 0 END REPEAT r', []];
        yield 'the label of an outer loop' => ['CREATE PROCEDURE p() o: LOOP i: LOOP ITERATE O; END LOOP; END LOOP', []];
        yield 'the label of a block' => ['CREATE PROCEDURE p() b: BEGIN l: LOOP ITERATE b; END LOOP; END', ['ITERATE with no matching label: b']];
        yield 'the label of a closed loop' => ['CREATE PROCEDURE p() b: BEGIN l: LOOP LEAVE b; END LOOP; ITERATE l; END', ['ITERATE with no matching label: l']];
        yield 'no label' => ['CREATE PROCEDURE p() ITERATE zz', ['ITERATE with no matching label: zz']];
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
        yield '5.7' => ['mysql-5.7.44', 'create procedure p() l: loop iterate l; end loop', 'CREATE PROCEDURE p() l: LOOP ITERATE l; END LOOP'];
        yield 'a quoted label in 9.1' => ['mysql-9.1.0', 'create procedure p() `my label`: loop iterate `my label`; end loop', 'CREATE PROCEDURE p() `my label`: LOOP ITERATE `my label`; END LOOP'];
    }
}
