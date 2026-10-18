<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Resolver;

use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use Deriver\Exception\ResolutionException;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[CoversClass(AssignmentPatterns::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Source::class)]
#[Small]
final class AssignmentPatternsTest extends TestCase
{
    #[DataProvider('providerProgramsDestructuringProgramsinvalid')]
    public function testEnterNodeRejectsPatternsBeforeExecutingAnyCallable(string $source, string $message): void
    {
        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage($message);
        (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', $source)));
    }
    #[DataProvider('providerValidPatterns')]
    public function testPatternAcceptsHomogeneousNestedAndKeyedPatterns(List_ $pattern): void
    {
        self::assertSame([], (new AssignmentPatterns(new Collecting()))->pattern($pattern));
    }
    /**
     * @return iterable<string,array{List_}>
     */
    public static function providerValidPatterns(): iterable
    {
        yield 'unkeyed holes' => [new List_([null, new ArrayItem(new Variable('x'))])];
        yield 'explicit keys' => [new List_([new ArrayItem(new Variable('x'), new Int_(2)), new ArrayItem(new Variable('y'), new Int_(4))])];
        yield 'nested lists' => [new List_([new ArrayItem(new List_([new ArrayItem(new Variable('x'))]))])];
    }
    #[DataProvider('providerSourceSyntax')]
    public function testSourceErrorRejectsInvalidRootsAndNullsafeChains(Expr $source, string $expected): void
    {
        self::assertSame($expected, (new AssignmentPatterns(new Collecting()))->sourceError($source));
    }
    /**
     * @return iterable<string,array{Expr,string}>
     */
    public static function providerSourceSyntax(): iterable
    {
        yield 'variable' => [new Variable('source'), ''];
        yield 'array element' => [new ArrayDimFetch(new Variable('source'), new Int_(0)), ''];
        yield 'property' => [new PropertyFetch(new Variable('source'), 'value'), ''];
        yield 'static property' => [new StaticPropertyFetch(new Name('Box'), 'value'), ''];
        yield 'function' => [new FuncCall(new Name('source')), ''];
        yield 'method' => [new MethodCall(new Variable('source'), 'value'), ''];
        yield 'static method' => [new StaticCall(new Name('Box'), 'value'), ''];
        yield 'literal' => [new Int_(1), 'Cannot assign reference to non referenceable value.'];
        yield 'array literal' => [new Array_([]), 'Cannot assign reference to non referenceable value.'];
        yield 'temporary property' => [new PropertyFetch(new New_(new Name('Box')), 'value'), 'Cannot use temporary expression in write context.'];
        yield 'temporary array element' => [new ArrayDimFetch(new Array_([]), new Int_(0)), 'Cannot use temporary expression in write context.'];
        yield 'nullsafe property' => [new NullsafePropertyFetch(new Variable('source'), 'value'), 'Cannot take reference of a nullsafe chain.'];
        yield 'nullsafe method' => [new NullsafeMethodCall(new Variable('source'), 'value'), 'Cannot take reference of a nullsafe chain.'];
        yield 'nested nullsafe chain' => [new ArrayDimFetch(new NullsafePropertyFetch(new Variable('source'), 'value'), new Int_(0)), 'Cannot take reference of a nullsafe chain.'];
    }
    #[DataProvider('providerReferencePatterns')]
    public function testReferencesFindsNestedReferenceLeavesWithoutTreatingEveryPatternAsShared(Expr $pattern, bool $expected): void
    {
        self::assertSame($expected, AssignmentPatterns::references($pattern));
    }
    /**
     * @return iterable<string,array{Expr,bool}>
     */
    public static function providerReferencePatterns(): iterable
    {
        yield 'ordinary variable' => [new Variable('x'), false];
        yield 'ordinary list' => [new List_([new ArrayItem(new Variable('x'))]), false];
        yield 'list holes' => [new List_([null, new ArrayItem(new Variable('x'))]), false];
        yield 'reference list' => [new List_([new ArrayItem(new Variable('x'), byRef: true)]), true];
        yield 'nested reference' => [new List_([null, new ArrayItem(new Array_([new ArrayItem(new Variable('x'), byRef: true)]))]), true];
        yield 'nested value' => [new List_([new ArrayItem(new List_([new ArrayItem(new Variable('x'))]))]), false];
    }
    /**
     * @return array<string,array{string,string}> Invalid source and target diagnostic fragment
     */
    public static function providerProgramsDestructuringProgramsinvalid(): array
    {
        return ['temporary object property source' => ['<?php class Box{public array $items=[1];}function target(){[&$x]=(new Box)->items;}', 'Cannot use temporary expression in write context'], 'literal reference source' => ['<?php function target(){[&$x]=[1];}', 'Cannot assign reference to non referenceable value'], 'scalar reference source' => ['<?php function target(){[&$x]=1;}', 'Cannot assign reference to non referenceable value'], 'nested literal reference source' => ['<?php function target(){[[&$x]]=[[1]];}', 'Cannot assign reference to non referenceable value'], 'empty pattern' => ['<?php function target(){[]=[1];}', 'Cannot use empty list'], 'all holes' => ['<?php function target(){list(,)=[1];}', 'Cannot use empty list'], 'mixed keyed pattern' => ['<?php function target(){[0=>$x,$y]=[1,2];}', 'Cannot mix keyed and unkeyed array entries'], 'keyed hole' => ['<?php function target(){[0=>$x, ,2=>$y]=[1,2,3];}', 'Cannot use empty array entries in keyed array assignment'], 'mixed nested style' => ['<?php function target(){list([$x])=[[1]];}', 'Cannot mix [] and list()'], 'spread pattern' => ['<?php function target(){[$x,...$y]=[1,2];}', 'Spread operator is not supported in assignments'], 'nullsafe reference source' => ['<?php function target($b){[&$x]=$b?->items;}', 'Cannot take reference of a nullsafe chain']];
    }
}
