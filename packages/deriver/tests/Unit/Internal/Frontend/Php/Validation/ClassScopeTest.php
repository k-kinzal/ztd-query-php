<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php\Validation;

use Deriver\Api\Project\TargetProfile;
use Deriver\Internal\Frontend\Php\Cache\SyntaxCache;
use Deriver\Internal\Frontend\Php\Validation\ClassScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Programs\ClassNamePrograms;

#[CoversClass(ClassScope::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[Small]
final class ClassScopeTest extends TestCase
{
    #[DataProviderExternal(ClassNamePrograms::class, 'invalid')]
    public function testEnterNodeRejectsUndefinedLexicalClassReferences(string $source, string $message): void
    {
        $tree = (new SyntaxCache())->parse($source, new TargetProfile());
        self::assertCount(1, $tree->errors);
        self::assertStringContainsString($message, $tree->errors[0]['message']);
        self::assertSame([], $tree->nodes);
    }

    public function testLeaveNodeRestoresTheEnclosingClassAfterClosuresAndNestedFunctions(): void
    {
        $errors = new \PhpParser\ErrorHandler\Collecting();
        $visitor = new ClassScope($errors);
        $class = new \PhpParser\Node\Stmt\Class_('Box', ['extends' => new \PhpParser\Node\Name('Base')]);
        $method = new \PhpParser\Node\Stmt\ClassMethod('value');
        $closure = new \PhpParser\Node\Expr\Closure();
        $function = new \PhpParser\Node\Stmt\Function_('nested');
        $visitor->enterNode($class);
        $visitor->enterNode($method);
        $methodScope = $visitor->scope;
        $visitor->enterNode($closure);
        $closureScope = $visitor->scope;
        $visitor->leaveNode($closure);
        $restored = $visitor->scope;
        $visitor->enterNode($function);
        $functionScope = $visitor->scope;
        $visitor->leaveNode($function);
        $visitor->leaveNode($method);
        $visitor->leaveNode($class);
        self::assertSame(['class' => true,'parent' => true,'trait' => false,'function' => 'method'], $methodScope);
        self::assertSame(['class' => true,'parent' => true,'trait' => false,'function' => 'closure'], $closureScope);
        self::assertSame($methodScope, $restored);
        self::assertSame(['class' => false,'parent' => false,'trait' => false,'function' => 'function'], $functionScope);
        self::assertSame(['class' => false,'parent' => false,'trait' => false,'function' => 'script'], $visitor->scope);
        self::assertSame([], $visitor->stack);
        self::assertSame([], $errors->getErrors());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerRebindableScopes')]
    public function testValidateDefersRebindableScopesAndOrdinaryClassNames(string $source): void
    {
        $tree = (new SyntaxCache())->parse($source, new TargetProfile());
        self::assertSame([], $tree->errors);
        self::assertNotEmpty($tree->nodes);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function providerRebindableScopes(): iterable
    {
        yield 'script' => ['<?php return self::class;'];
        yield 'closure' => ['<?php function target(){return function(){return parent::class;};}'];
        yield 'arrow' => ['<?php function target(){return fn()=>static::class;}'];
        yield 'trait' => ['<?php trait T{function value(){return parent::class;}}'];
        yield 'parent method' => ['<?php class Base{}class Box extends Base{function value(){return parent::class;}}'];
        yield 'ordinary unknown name' => ['<?php function target(){return Missing::class;}'];
        yield 'dynamic name' => ['<?php function target($x){return $x::class;}'];
        yield 'anonymous class' => ['<?php function target(){return new class{function value(){return self::class;}};}'];
    }
}
