<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation\Objects;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\FreeNames;
use SqlCatalog\Analysis\Derivation\Objects\ObjectEffects;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\SourceParser;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(ObjectEffects::class)]
#[UsesClass(Domain::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(PatternTerm::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextGeneralization::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(FreeNames::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
final class ObjectEffectsTest extends TestCase
{
    public function testWritesFollowsAliasesStoredInArraysInsideFunctionScopes(): void
    {
        $file = (new SourceParser())->parse('query.php', '<?php function run($q) { $box = [$q]; $alias = $box[0]; $alias->where("id", 1); }');
        $calls = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class);
        $writes = (new ObjectEffects([$file]))->writes($calls[0]);
        self::assertArrayHasKey('q', $writes);
        self::assertArrayHasKey('box', $writes);
        self::assertArrayHasKey('alias', $writes);
        self::assertSame([], (new ObjectEffects())->writes(new \PhpParser\Node\Scalar\String_('value')));
    }

    public function testRootFindsTheBaseOfFluentCallsAndArrayElements(): void
    {
        $effects = new ObjectEffects();
        $q = new \PhpParser\Node\Expr\Variable('q');
        self::assertSame('q', $effects->root(new \PhpParser\Node\Expr\MethodCall(new \PhpParser\Node\Expr\ArrayDimFetch($q), 'where')));
        self::assertNull($effects->root(new \PhpParser\Node\Expr\Clone_($q)));
    }

    public function testOwnerStopsAtTheEnclosingFunction(): void
    {
        $file = (new SourceParser())->parse('query.php', '<?php function run($q) { $q->get(); }');
        $function = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $function);
        $calls = (new \PhpParser\NodeFinder())->findInstanceOf($function->stmts, \PhpParser\Node\Expr\MethodCall::class);
        self::assertSame($function, (new ObjectEffects())->owner($calls[0]));
    }

    public function testGraphDoesNotTreatClonesAsAliases(): void
    {
        $file = (new SourceParser())->parse('query.php', '<?php function run($q) { $alias = $q; $copy = clone $q; }');
        $graph = (new ObjectEffects())->graph($file->statements[0]);
        self::assertSame(['alias' => true], $graph['q']);
        self::assertSame(['q' => true], $graph['alias']);
        self::assertArrayNotHasKey('copy', $graph);
    }

    public function testStoredRootsIncludesNestedArrayReferences(): void
    {
        $array = new \PhpParser\Node\Expr\Array_([new \PhpParser\Node\ArrayItem(new \PhpParser\Node\Expr\Variable('q'))]);
        self::assertSame(['q'], (new ObjectEffects())->storedRoots($array));
    }

    public function testAssignmentsExcludesNestedFunctionsAndClasses(): void
    {
        $file = (new SourceParser())->parse('query.php', '<?php function run($q) { $alias = $q; function inner($x) { $other = $x; } }');
        self::assertCount(1, (new ObjectEffects())->assignments($file->statements[0], true));
        self::assertSame([], (new ObjectEffects())->assignments($file->statements[0]));
    }

    public function testWritesIncludesFileScopeAliasesWithoutCrossingIntoFunctions(): void
    {
        $file = (new SourceParser())->parse('query.php', '<?php $q = make(); $alias = $q; $alias->where("id", 1);');
        $call = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class)[0];
        $writes = (new ObjectEffects([$file]))->writes($call);
        ksort($writes);
        self::assertSame(['alias' => true, 'q' => true], $writes);
    }

    public function testWritesIncludesCapturedObjectsAndPropertyMutationReceivers(): void
    {
        $q = new \PhpParser\Node\Expr\Variable('q');
        $effects = new ObjectEffects();
        self::assertSame(['q' => true], $effects->writes(new \PhpParser\Node\Expr\PropertyFetch($q, 'wheres')));
        self::assertSame(['q' => true], $effects->writes(new \PhpParser\Node\Expr\NullsafeMethodCall($q, 'where')));
        $closure = new \PhpParser\Node\Expr\Closure(['uses' => [new \PhpParser\Node\Expr\ClosureUse($q)]]);
        self::assertSame(['q' => true], $effects->writes(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('unknown'), [new \PhpParser\Node\Arg($closure)])));
        $arrow = new \PhpParser\Node\Expr\ArrowFunction(['expr' => $q]);
        self::assertSame(['q' => true], $effects->writes(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('unknown'), [new \PhpParser\Node\Arg($arrow)])));
        self::assertSame([], $effects->writes(new \PhpParser\Node\Expr\MethodCall($q, 'where', [new \PhpParser\Node\VariadicPlaceholder()])));
    }

    public function testStoredRootsPreservesEveryObjectReferenceInAnArray(): void
    {
        $array = new \PhpParser\Node\Expr\Array_([new \PhpParser\Node\ArrayItem(new \PhpParser\Node\Expr\Variable('a')), new \PhpParser\Node\ArrayItem(new \PhpParser\Node\Expr\Variable('b'))]);
        self::assertSame(['a', 'b'], (new ObjectEffects())->storedRoots($array));
    }
}
