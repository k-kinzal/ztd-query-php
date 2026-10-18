<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Lowerer\Declaration;

use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ReferencesTest extends TestCase
{
    public function testAddIndexesNestedClosuresWithoutLoweringAnyBody(): void
    {
        $source = new Source('fixture.php', '<?php function outer(){ $a=1; return fn()=>function()use($a){return sink($a);}; } function unrelated(){other();}');
        $index = new Index('test', [(new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source))]);
        $owners = $index->callOwners('sink');
        self::assertCount(1, $owners);
        $parents = $index->closureOwners($owners[0]);
        self::assertCount(1, $parents);
        self::assertSame(['outer'], $index->closureOwners($parents[0]));
        self::assertSame([], $index->closureOwners('missing'));
        self::assertSame(0, $index->graphCount());
    }

    public function testCallsRetainNamespaceFallbacksAndLexicalConstructors(): void
    {
        $source = new Source('fixture.php', '<?php namespace N; use function M\\sink as imported; function a(){sink();} function b(){imported();} class C {function create(){return new self;} function other(){return new Other;}}');
        $index = new Index('test', [(new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source))]);
        self::assertSame(['N\\a'], $index->callOwners('sink'));
        self::assertSame(['N\\a'], $index->callOwners('\\n\\SINK'));
        self::assertSame(['N\\b'], $index->callOwners('M\\sink'));
        self::assertSame(['N\\C::create'], $index->callOwners('N\\C::__construct'));
        self::assertSame(['N\\C::create', 'N\\C::other'], $index->callOwners('N\\Other::__construct'));
        self::assertSame([], $index->callOwners('absent'));
        self::assertSame(0, $index->graphCount());
    }

    public function testPropertiesPreserveDynamicNamesAndClosureBoundaries(): void
    {
        $source = new Source('fixture.php', '<?php class C {function __construct(public $x){} function dynamic($n){$this->$n=1;} function nested(){return function(){$this->x[0]+=1;};} function uppercase(){$this->X=2;}}');
        $index = new Index('test', [(new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source))]);
        $owners = $index->propertyOwners('x');
        self::assertCount(3, $owners);
        self::assertContains('C::__construct', $owners);
        self::assertContains('C::dynamic', $owners);
        self::assertNotContains('C::nested', $owners);
        self::assertNotContains('C::uppercase', $owners);
        self::assertSame(['C::dynamic', 'C::uppercase'], $index->propertyOwners('X'));
        self::assertSame(['C::dynamic'], $index->propertyOwners('absent'));
        self::assertSame(0, $index->graphCount());
    }

    public function testGlobalsDoNotIncludeLocalsInFunctionsOrClosures(): void
    {
        $parser = new Parser(new ParserConfig('8.3'));
        $sources = [new Source('a.php', '<?php $wanted=1;'), new Source('b.php', '<?php function f(){$wanted=2;} $c=function(){$wanted=3;};'), new Source('c.php', '<?php $$name=4;')];
        $index = new Index('test', array_map(static fn (Source $source) => (new Resolver())->resolve($parser->parse($source)), $sources));
        self::assertSame(['script:a.php', 'script:c.php'], $index->globalOwners('wanted'));
        self::assertSame(['script:c.php'], $index->globalOwners('absent'));
        self::assertSame(0, $index->graphCount());
    }

    public function testNestedAnonymousMethodsExtendTheIndexesWithoutRebuildingOtherBodies(): void
    {
        $source = new Source('fixture.php', '<?php function make(){return new class {function run(){return sink();}};} function unrelated(){other();}');
        $index = new Index('test', [(new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source))]);
        $before = $index->callOwners('sink');
        $graph = $index->callable('make');
        $owners = $index->callOwners('sink');
        self::assertSame([], $before);
        self::assertNotNull($graph);
        self::assertCount(1, $owners);
        self::assertStringEndsWith('::run', $owners[0]);
        self::assertSame(1, $index->graphCount());
    }

    public function testClosuresKeepTraitConsumerScopesSeparate(): void
    {
        $source = new Source('fixture.php', '<?php trait T {function make(){return fn()=>sink();}} class A {use T;} class B {use T;}');
        $index = new Index('test', [(new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source))]);
        $owners = $index->callOwners('sink');
        self::assertCount(3, $owners);
        $parents = array_map($index->closureOwners(...), $owners);
        self::assertContains(['A::make'], $parents);
        self::assertContains(['B::make'], $parents);
        self::assertContains(['T::make'], $parents);
        self::assertSame(0, $index->graphCount());
    }

    public function testPropertyIndexesMutationFormsWithoutReadOnlyUses(): void
    {
        $source = new Source('fixture.php', '<?php class C {function change(){++$this->x; $this->y+=1; self::$z[0]=2;} function read(){return $this->x;}}');
        $index = new Index('test', [(new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source))]);
        self::assertSame(['C::change'], $index->propertyOwners('x'));
        self::assertSame(['C::change'], $index->propertyOwners('y'));
        self::assertSame(['C::change'], $index->propertyOwners('z'));
    }
}
