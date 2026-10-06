<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Facade\Splitter;
use SqlSemantics\Platform\Sqlite\Dialect;

#[CoversClass(Splitter::class)]
#[Medium]
final class SplitterTest extends TestCase
{
    public function testSplitEndsEachStatementWithItsOwnTerminator(): void
    {
        $splitter = new Splitter(Platforms::of('sqlite')->parser((new Semantics(Dialect::Sqlite))->profile()));

        self::assertSame(['SELECT 1;', ' SELECT 2;', ' SELECT 3'], $splitter->split('SELECT 1; SELECT 2; SELECT 3'));
    }

    public function testSplitKeepsTrailingWhitespaceAndCommentsWithTheLastStatement(): void
    {
        $splitter = new Splitter(Platforms::of('sqlite')->parser((new Semantics(Dialect::Sqlite))->profile()));

        self::assertSame(['SELECT 1;', " SELECT 2; -- done\n"], $splitter->split("SELECT 1; SELECT 2; -- done\n"));
        self::assertSame(['SELECT 1;'], $splitter->split('SELECT 1;'));
    }

    public function testSplitIgnoresTerminatorsInsideStringsCommentsAndCompoundStatements(): void
    {
        $splitter = new Splitter(Platforms::of('sqlite')->parser((new Semantics(Dialect::Sqlite))->profile()));

        self::assertSame(["SELECT ';';", ' /* a; b */ SELECT 2'], $splitter->split("SELECT ';'; /* a; b */ SELECT 2"));
        self::assertSame(['CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT 1; SELECT 2; END;', ' SELECT 3'], $splitter->split('CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT 1; SELECT 2; END; SELECT 3'));
    }

    public function testSplitOfAnEmptyOrBlankInputHasNoStatements(): void
    {
        $splitter = new Splitter(Platforms::of('sqlite')->parser((new Semantics(Dialect::Sqlite))->profile()));

        self::assertSame([], $splitter->split(''));
        self::assertSame([], $splitter->split("  \n"));
    }
}
