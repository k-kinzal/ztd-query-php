<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * Property mutations, reference obligations, and one-time static initialization.
 */
#[CoversNothing]
#[Small]
final class PropertyMutationTest extends TestCase
{
    /**
     * @param string $source
     * @param Term $expected
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[DataProvider('providerTargetSemantics')]
    public function testTargetSemantics(string $source, Term $expected): void
    {
        $result = Analysis::returns($source);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertSame([], $result->frontiers);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected->native(), $result->normalOutcomes[0]->values['return']->native());
        self::assertSame('closed', $result->assessment->closure);
    }

    /**
     * @return array<string, array{string, Term}>
     */
    public static function providerTargetSemantics(): array
    {
        return [
            'privateIsset' => ['<?php class B{private $x=1;}function target(){$b=new B;return [isset($b->x),$b->x??9,empty($b->x)];}', Term::fromNative([false, 9, true])],
            'nullIsset' => ['<?php function target(){$b=null;return [isset($b->x),$b->x??9,empty($b->x)];}', Term::fromNative([false, 9, true])],
            'scalarWrite' => ['<?php function target(){$b=1;try{$b->x=2;}catch(Error $e){return $b;}return 999;}', Term::fromNative(1)],
            'nullWrite' => ['<?php function target(){$b=null;try{$b->x=2;}catch(Error $e){return $b;}return 999;}', Term::fromNative(null)],
            'scalarClone' => ['<?php function target(){$b=1;try{$c=clone $b;}catch(Error $e){return $b;}return 999;}', Term::fromNative(1)],
            'readonlyClass' => ['<?php readonly class B{function __construct(public int $x){}}function target(){$b=new B(1);try{$b->x=2;}catch(Error $e){return $b->x;}return 999;}', Term::fromNative(1)],
            'readonlyArray' => ['<?php class B{function __construct(public readonly array $x){}}function target(){$b=new B([1]);try{$b->x[]=2;}catch(Error $e){return $b->x;}return 999;}', Term::fromNative([1])],
            'readonlyNested' => ['<?php class B{function __construct(public readonly array $x){}}function target(){$b=new B([[1]]);try{$b->x[0][0]=2;}catch(Error $e){return $b->x;}return 999;}', Term::fromNative([[1]])],
            'readonlyUnsetElement' => ['<?php class B{function __construct(public readonly array $x){}}function target(){$b=new B([1]);try{unset($b->x[0]);}catch(Error $e){return $b->x;}return 999;}', Term::fromNative([1])],
            'readonlyReference' => ['<?php class B{function __construct(public readonly int $x){}}function target(){$b=new B(1);try{$ref=&$b->x;}catch(Error $e){return $b->x;}return 999;}', Term::fromNative(1)],
            'readonlyUnsetUninitialized' => ['<?php class B{public readonly int $x;function initialize(){unset($this->x);$this->x=3;}}function target(){$b=new B;$b->initialize();return $b->x;}', Term::fromNative(3)],
            'typedIncrementOverflow' => ['<?php class B{public int $x=9223372036854775807;}function target(){$b=new B;try{$b->x++;}catch(TypeError $e){return $b->x;}return 999;}', Term::fromNative(9223372036854775807)],
            'typedReference' => ['<?php class B{public int $x=1;}function target(){$b=new B;$ref=&$b->x;try{$ref=[];}catch(TypeError $e){return $b->x;}return 999;}', Term::fromNative(1)],
            'typedReferenceCoercion' => ['<?php class B{public int $x=1;}function target(){$b=new B;$ref=&$b->x;$ref="42";return [$b->x,$ref];}', Term::fromNative([42, 42])],
            'enumParameter' => ['<?php enum E{case A;}function f(E $x){return $x===E::A;}function target(){return f(E::A);}', Term::fromNative(true)],
            'callableParameter' => ['<?php function f(callable $g){return $g();}function g(){return 3;}function target(){return f("g");}', Term::fromNative(3)],
            'staticInitializer' => ['<?php function nextValue(){static $n=0;return ++$n;}function f(){static $n=nextValue();return $n;}function target(){return [f(),f(),nextValue()];}', Term::fromNative([1, 1, 2])],
            'readonlyByReference' => ['<?php class B{function __construct(public readonly int $x){}}function change(&$x){$x=2;}function target(){$b=new B(1);try{change($b->x);}catch(Error $e){return $b->x;}return 999;}', Term::fromNative(1)],
            'propertyElementWrite' => ['<?php class B{public array $x=[1];}function target(){$b=new B;$b->x[0]=2;return $b->x;}', Term::fromNative([2])],
            'referenceCalleeWrite' => ['<?php class B{public int $x=1;}function change(&$x){$x=[];}function target(){$b=new B;try{change($b->x);}catch(TypeError $e){return $b->x;}return 999;}', Term::fromNative(1)],
            'referenceCalleeCoercion' => ['<?php class B{public int $x=1;}function change(&$x){$x="42";}function target(){$b=new B;change($b->x);return $b->x;}', Term::fromNative(42)],
            'referenceDestinationType' => ['<?php class B{public int $x=1;}function target(){$b=new B;$x="42";$b->x=&$x;return [$b->x,$x];}', Term::fromNative([42, 42])],
            'referenceDestinationInvalid' => ['<?php class B{public int $x=1;}function target(){$b=new B;$x=[];try{$b->x=&$x;}catch(TypeError $e){return [$b->x,$x];}return 999;}', Term::fromNative([1, []])],
            'referenceConflictingCoercions' => ['<?php class B{public int $x=1;public float $y=2.0;}function target(){$b=new B;try{$b->x=&$b->y;}catch(TypeError $e){return [$b->x,$b->y];}return 999;}', Term::fromNative([1, 2.0])],
            'referenceUnsetRemovesConstraint' => ['<?php class B{public int $x=1;}function target(){$b=new B;$x=&$b->x;unset($b->x);$x="unbound";return $x;}', Term::fromNative('unbound')],
            'referenceCloneKeepsConstraint' => ['<?php class B{public int $x=1;}function target(){$b=new B;$x=&$b->x;$c=clone $b;unset($b->x);try{$x=[];}catch(TypeError $e){return $c->x;}return 999;}', Term::fromNative(1)],
        ];
    }
}
