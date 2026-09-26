<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis\Effect;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\Effect\ReferenceEffects;
use SqlCatalog\Core\Php\SourceParser;

#[CoversClass(ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExternalInput::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
final class ReferenceEffectsTest extends TestCase
{
    public function testAffectedFindsAliasesAcrossStatementsAndCachesTheBody(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php function f() { $a =& $b; $b = "tail"; }');
        $body = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $body);
        $statement = $body->stmts[1];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $effects = new ReferenceEffects();
        self::assertSame(['a' => true, 'b' => true], $effects->affected($statement->expr, ['b' => true]));
        self::assertSame([], $effects->affected($statement->expr, ['unrelated' => true]));
        self::assertSame(['a' => true, 'b' => true], $effects->affected($statement->expr, ['a' => true]));
    }

    public function testCollectIncludesEscapedCapturesButNotNestedBodies(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php function f() { $cb = function () use (&$a) { $b =& $c; }; foreach ($items as &$item) {} }');
        $body = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $body);
        self::assertSame(['a' => true, 'item' => true, 'items' => true], (new ReferenceEffects())->collect($body, true));
        self::assertSame([], (new ReferenceEffects())->collect(new Expr\ArrowFunction(['expr' => new Expr\Variable('x')])));
    }

    public function testAffectedSharesReferencesAcrossFileScopeSiblings(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php $a =& $b; $b = "changed";');
        self::assertSame(['a' => true, 'b' => true], (new ReferenceEffects())->affected($file->statements[1], ['b' => true]));
        self::assertSame([], (new ReferenceEffects())->affected(new Expr\Variable('x'), ['x' => true]));
    }

    public function testCollectLeavesByValueCapturesAndNestedFunctionsIndependent(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php function outer() { $f = function () use ($value) { $a =& $b; }; function inner() { $c =& $d; } }');
        self::assertSame([], (new ReferenceEffects())->collect($file->statements[0], true));
    }

    public function testCollectRetainsByReferenceCapturesInsideTheClosureBody(): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php $callback = function () use (&$tail, $other) { unknown(); };');
        $closure = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, Expr\Closure::class);
        self::assertInstanceOf(Expr\Closure::class, $closure);
        self::assertSame(['tail' => true], (new ReferenceEffects())->collect($closure, true));
        self::assertSame(['tail' => true], (new ReferenceEffects())->affected($closure->stmts[0], ['tail' => true]));
    }

}
