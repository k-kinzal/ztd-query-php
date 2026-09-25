<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis\Derivation;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\Derivation\SourceTree;
use SqlCatalog\Core\Php\ParsedFile;
use SqlCatalog\Core\Php\SourceParser;

#[CoversClass(SourceTree::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(SourceParser::class)]
final class SourceTreeTest extends TestCase
{
    public function testAnchorOfIsTheStatementANodeIsPartOf(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f() { $a = 1; $b = g($a); }');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);

        $anchor = (new SourceTree([$file]))->anchorOf($call);

        self::assertInstanceOf(Stmt\Expression::class, $anchor);
        self::assertSame($call, $anchor->expr instanceof Expr\Assign ? $anchor->expr->expr : null);
    }

    public function testAnchorOfStopsAtAnArrowFunctionWithNoStatements(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $f = fn ($x) => g($x);');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);

        self::assertInstanceOf(Expr\ArrowFunction::class, (new SourceTree([$file]))->anchorOf($call));
    }

    public function testAnchorOfIsNothingForANodeOutsideAnyStatement(): void
    {
        self::assertNull((new SourceTree())->anchorOf(new Expr\Variable('a')));
    }

    public function testIsListedLeavesOutTheArmsOfABranch(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php if ($a) { } else { }');
        $if = $file->statements[0];
        self::assertInstanceOf(Stmt\If_::class, $if);
        self::assertNotNull($if->else);
        $tree = new SourceTree([$file]);

        self::assertTrue($tree->isListed($if));
        self::assertFalse($tree->isListed($if->else));
    }

    public function testLocateFindsATopLevelStatementInItsFile(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = 1; $b = 2;');

        $location = (new SourceTree([$file]))->locate($file->statements[1]);

        self::assertNotNull($location);
        self::assertNull($location[0]);
        self::assertSame(1, $location[2]);
    }

    public function testLocateFindsANestedStatementInTheListThatHoldsIt(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f() { $a = 1; $b = 2; }');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);

        $location = (new SourceTree([$file]))->locate($function->stmts[1]);

        self::assertNotNull($location);
        self::assertSame($function, $location[0]);
        self::assertSame(1, $location[2]);
    }

    public function testLocateIsNothingForAStatementNotInAnyRecordedFile(): void
    {
        self::assertNull((new SourceTree())->locate(new Stmt\Nop()));
    }

    public function testListOfReadsTheStatementsEveryKindOfOwnerRuns(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php while ($a) { $b = 1; } declare(ticks=1) { $c = 1; }');
        $tree = new SourceTree([$file]);

        self::assertCount(1, $tree->listOf($file->statements[0]));
        self::assertCount(1, $tree->listOf($file->statements[1]));
        self::assertSame([], $tree->listOf(new Expr\Variable('a')));
    }

    public function testFileOfNamesTheFileANodeIsWrittenIn(): void
    {
        $file = (new SourceParser())->parse('src/a.php', '<?php namespace App; function f() { g(); }');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $tree = new SourceTree([$file]);

        self::assertSame('src/a.php', $tree->fileOf($call));
        self::assertSame('', $tree->fileOf(new Expr\Variable('a')));
    }

    public function testBodyOfIsTheFunctionANodeIsWrittenIn(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f() { g(); } h();');
        $calls = (new NodeFinder())->findInstanceOf($file->statements, Expr\FuncCall::class);
        $tree = new SourceTree([$file]);

        self::assertSame($file->statements[0], $tree->bodyOf($calls[0]));
        self::assertNull($tree->bodyOf($calls[1]));
    }
}
