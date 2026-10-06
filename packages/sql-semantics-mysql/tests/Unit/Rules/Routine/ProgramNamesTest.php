<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ProgramNames::class)]
#[Medium]
final class ProgramNamesTest extends TestCase
{
    public function testQualifiedWritesTheDatabaseQualifierWhenPresent(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql910));
        (new ProgramNames())->qualified($out, new QualifiedName(new Name('p'), new Name('db')));
        (new ProgramNames())->qualified($out, new QualifiedName(new Name('select')));
        (new ProgramNames())->qualified($out, new QualifiedName(new Name('t'), new Name('db')), NameUse::Relation);

        self::assertSame('db.p `select` db.t', (new Lexical())->join($out->pieces()));
    }

    public function testLabelWritesTheLabelFollowedByAColon(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql910));
        (new ProgramNames())->label($out, new Name('l'));
        (new ProgramNames())->label($out, null);
        (new ProgramNames())->label($out, new Name('select l'));

        self::assertSame('l: `select l`:', (new Lexical())->join($out->pieces()));
    }

    public function testEndWritesTheEndLabelWhenPresent(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql910));
        $out->keyword('END');
        (new ProgramNames())->end($out, new Name('l'));
        (new ProgramNames())->end($out, null);

        self::assertSame('END l', (new Lexical())->join($out->pieces()));
        self::assertSame('CREATE PROCEDURE p() l: BEGIN END l', (new Semantics(Dialect::MySql))->analyze('create procedure p() l: begin end l')->toString());
    }

    public function testTypeWritesTheCollateClauseWhenPresent(): void
    {
        $function = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f() RETURNS CHAR(1) COLLATE utf8mb4_bin RETURN 1')->statement;
        self::assertInstanceOf(CreateFunction::class, $function);
        $out = new Output(new Codec(GrammarRelease::MySql910));
        (new ProgramNames())->type($out, $function->returns, $function->collation);
        (new ProgramNames())->type($out, $function->returns, null);

        self::assertSame('CHAR(1) COLLATE utf8mb4_bin CHAR(1)', (new Lexical())->join($out->pieces()));
    }
}
