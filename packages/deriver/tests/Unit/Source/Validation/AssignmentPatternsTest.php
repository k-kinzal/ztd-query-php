<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Validation;

use Deriver\Project\TargetProfile;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Validation\AssignmentPatterns;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Programs\DestructuringPrograms;

#[CoversClass(AssignmentPatterns::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[Small]
final class AssignmentPatternsTest extends TestCase
{
    #[DataProviderExternal(DestructuringPrograms::class, 'invalid')]
    public function testEnterNodeRejectsPatternsBeforeExecutingAnyCallable(string $source, string $message): void
    {
        $tree = (new SyntaxCache())->parse($source, new TargetProfile());
        self::assertCount(1, $tree->errors);
        self::assertStringContainsString($message, $tree->errors[0]['message']);
        self::assertSame([], $tree->nodes);
    }

    #[DataProvider('providerValidPatterns')]
    public function testPatternAcceptsHomogeneousNestedAndKeyedPatterns(Expr\List_ $pattern): void
    {
        self::assertSame([], (new AssignmentPatterns(new Collecting()))->pattern($pattern));
    }

    /**
     * @return iterable<string,array{Expr\List_}>
     */
    public static function providerValidPatterns(): iterable
    {
        yield 'unkeyed holes' => [new Expr\List_([null, new ArrayItem(new Expr\Variable('x'))])];
        yield 'explicit keys' => [new Expr\List_([new ArrayItem(new Expr\Variable('x'), new Int_(2)), new ArrayItem(new Expr\Variable('y'), new Int_(4))])];
        yield 'nested lists' => [new Expr\List_([new ArrayItem(new Expr\List_([new ArrayItem(new Expr\Variable('x'))]))])];
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
        yield 'variable' => [new Expr\Variable('source'), ''];
        yield 'array element' => [new Expr\ArrayDimFetch(new Expr\Variable('source'), new Int_(0)), ''];
        yield 'property' => [new Expr\PropertyFetch(new Expr\Variable('source'), 'value'), ''];
        yield 'static property' => [new Expr\StaticPropertyFetch(new Name('Box'), 'value'), ''];
        yield 'function' => [new Expr\FuncCall(new Name('source')), ''];
        yield 'method' => [new Expr\MethodCall(new Expr\Variable('source'), 'value'), ''];
        yield 'static method' => [new Expr\StaticCall(new Name('Box'), 'value'), ''];
        yield 'literal' => [new Int_(1), 'Cannot assign reference to non referenceable value.'];
        yield 'array literal' => [new Expr\Array_([]), 'Cannot assign reference to non referenceable value.'];
        yield 'temporary property' => [new Expr\PropertyFetch(new Expr\New_(new Name('Box')), 'value'), 'Cannot use temporary expression in write context.'];
        yield 'temporary array element' => [new Expr\ArrayDimFetch(new Expr\Array_([]), new Int_(0)), 'Cannot use temporary expression in write context.'];
        yield 'nullsafe property' => [new Expr\NullsafePropertyFetch(new Expr\Variable('source'), 'value'), 'Cannot take reference of a nullsafe chain.'];
        yield 'nullsafe method' => [new Expr\NullsafeMethodCall(new Expr\Variable('source'), 'value'), 'Cannot take reference of a nullsafe chain.'];
        yield 'nested nullsafe chain' => [new Expr\ArrayDimFetch(new Expr\NullsafePropertyFetch(new Expr\Variable('source'), 'value'), new Int_(0)), 'Cannot take reference of a nullsafe chain.'];
    }
}
