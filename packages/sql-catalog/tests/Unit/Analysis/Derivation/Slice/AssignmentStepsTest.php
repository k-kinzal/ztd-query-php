<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation\Slice;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\FreeNames;
use SqlCatalog\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps;
use SqlCatalog\Analysis\Derivation\Slice\Pending;
use SqlCatalog\Analysis\Derivation\Slice\SliceStep;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\SourceParser;

#[CoversClass(AssignmentSteps::class)]
#[UsesClass(FreeNames::class)]
#[UsesClass(ModifiedNames::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(Pending::class)]
#[UsesClass(SliceStep::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(SourceParser::class)]
final class AssignmentStepsTest extends TestCase
{
    public function testOverWalksBackOverTheLastAssignmentFirst(): void
    {
        $statement = (new SourceParser())->parse('t.php', '<?php $a = $b = $c; ')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $steps = new AssignmentSteps(new FreeNames(), new ModifiedNames());

        $outer = $steps->over($statement->expr, Pending::needing(['a' => true]));
        $both = $steps->over($statement->expr, Pending::needing(['a' => true, 'b' => true]));

        self::assertSame(['c'], array_keys($outer->needs));
        self::assertCount(1, $outer->steps);
        self::assertCount(2, $both->steps);
        self::assertSame($statement->expr, $both->steps[0]->node);
    }

    public function testReplacesIsTrueOnlyForAnAssignmentThatOverwritesItsTarget(): void
    {
        $steps = new AssignmentSteps(new FreeNames(), new ModifiedNames());

        self::assertTrue($steps->replaces(new Expr\Assign(new Expr\Variable('a'), new Expr\Variable('b'))));
        self::assertTrue($steps->replaces(new Expr\AssignRef(new Expr\Variable('a'), new Expr\Variable('b'))));
        self::assertFalse($steps->replaces(new Expr\Assign(new Expr\ArrayDimFetch(new Expr\Variable('a')), new Expr\Variable('b'))));
        self::assertFalse($steps->replaces(new Expr\AssignOp\Concat(new Expr\Variable('a'), new Expr\Variable('b'))));
    }

    public function testWithinLeavesOutWhatAClosureAssigns(): void
    {
        $statement = (new SourceParser())->parse('t.php', '<?php f($a = 1, function () { $b = 2; }, fn () => $c = 3);')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);

        self::assertCount(1, (new AssignmentSteps(new FreeNames(), new ModifiedNames()))->within($statement->expr));
    }

    public function testDeclarationDefinesWhatAGlobalListsButKeepsLookingPastAnUnset(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php global $db; unset($db);');
        $global = $file->statements[0];
        $unset = $file->statements[1];
        self::assertInstanceOf(Stmt\Global_::class, $global);
        self::assertInstanceOf(Stmt\Unset_::class, $unset);
        $steps = new AssignmentSteps(new FreeNames(), new ModifiedNames());

        self::assertSame([], $steps->declaration($global, Pending::needing(['db' => true]))->needs);
        self::assertSame(['db' => true], $steps->declaration($unset, Pending::needing(['db' => true]))->needs);
    }
}
