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
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[CoversClass(ClassScope::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Source::class)]
#[Small]
final class ClassScopeTest extends TestCase
{
    #[DataProvider('providerProgramsClassNameProgramsinvalid')]
    public function testEnterNodeRejectsUndefinedLexicalClassReferences(string $source, string $message): void
    {
        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage($message);
        (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', $source)));
    }
    public function testLeaveNodeRestoresTheEnclosingClassAfterClosuresAndNestedFunctions(): void
    {
        $errors = new Collecting();
        $visitor = new ClassScope($errors);
        $class = new Class_('Box', ['extends' => new Name('Base')]);
        $method = new ClassMethod('value');
        $closure = new Closure();
        $function = new Function_('nested');
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
        self::assertSame(['class' => true, 'parent' => true, 'trait' => false, 'function' => 'method'], $methodScope);
        self::assertSame(['class' => true, 'parent' => true, 'trait' => false, 'function' => 'closure'], $closureScope);
        self::assertSame($methodScope, $restored);
        self::assertSame(['class' => false, 'parent' => false, 'trait' => false, 'function' => 'function'], $functionScope);
        self::assertSame(['class' => false, 'parent' => false, 'trait' => false, 'function' => 'script'], $visitor->scope);
        self::assertSame([], $visitor->stack);
        self::assertSame([], $errors->getErrors());
    }
    #[DataProvider('providerRebindableScopes')]
    public function testValidateDefersRebindableScopesAndOrdinaryClassNames(string $source): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', $source)));
        self::assertSame($source, $tree->source->contents);
        self::assertNotEmpty($tree->stmts);
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
    /**
     * @return array<string,array{string,string}> Invalid source and required compiler diagnostic fragment
     */
    public static function providerProgramsClassNameProgramsinvalid(): array
    {
        return ['free self' => ['<?php function target(){return self::class;}', 'no class scope'], 'free parent' => ['<?php function target(){return parent::class;}', 'no class scope'], 'free static' => ['<?php function target(){return static::class;}', 'no class scope'], 'missing parent' => ['<?php class Box{function value(){return parent::class;}}function target(){return 1;}', 'has no parent'], 'nested named function' => ['<?php class Box{function value(){function nested(){return self::class;}}}function target(){return 1;}', 'no class scope'], 'free static call' => ['<?php function target(){return self::value();}', 'no class scope'], 'free allocation' => ['<?php function target(){return new self;}', 'no class scope'], 'free static property' => ['<?php function target(){return parent::$value;}', 'no class scope'], 'free type test' => ['<?php function target(){return new stdClass instanceof self;}', 'no class scope'], 'missing parent allocation' => ['<?php class Box{function value(){return new parent;}}function target(){return 1;}', 'has no parent'], 'missing parent initializer' => ['<?php class Box{const VALUE=parent::class;}function target(){return 1;}', 'has no parent'], 'free default initializer' => ['<?php function value($x=self::class){}function target(){return 1;}', 'no class scope']];
    }
}
