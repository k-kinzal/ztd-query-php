<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Control;

use Deriver\Internal\IR\ExceptionRegion;
use Deriver\Internal\Memory\Location;
use Deriver\Internal\Solver\Completion;
use Deriver\Internal\Solver\Control\ExceptionChain;
use Deriver\Internal\Solver\Control\Handler;
use Deriver\Internal\Solver\Control\Unwinding;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(ExceptionChain::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(Handler::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(Term::class)]
#[Small]
final class ExceptionChainTest extends TestCase
{
    public function testUnwindLinksNativeFailuresWhilePreservingTheThrownObject(): void
    {
        $program = SolverFixture::context()->program;
        $state = new State();
        $unwinding = new Unwinding($program);
        $original = $unwinding->capture($state, new Term('throwable', 'RuntimeException'));
        $replacement = $unwinding->capture($state, new Term('throwable', 'Error'));
        $state->completion = new Completion('throw', $replacement);
        $handler = new Handler(new ExceptionRegion([], 5, 6), 'finally', new Completion('throw', $original));
        (new ExceptionChain($program))->unwind($state, $handler);
        self::assertSame($replacement, $state->completion->value);
        self::assertSame($original, $state->memory->read(new Location('object:' . $replacement->literal, ['Error::previous'])));
    }

    /**
     * @param string $phase Handler phase
     * @param string $saved Saved completion kind
     * @param string $current Current completion kind
     */
    #[DataProvider('providerUnchangedCompletions')]
    public function testUnwindLeavesUnrelatedCompletionsUnchanged(string $phase, string $saved, string $current): void
    {
        $state = new State();
        $completion = new Completion($current, Term::constant(1), 9, 2);
        $state->completion = $completion;
        $handler = new Handler(new ExceptionRegion([], 5, 6), $phase, new Completion($saved, Term::constant(2)));
        (new ExceptionChain(SolverFixture::context()->program))->unwind($state, $handler);
        self::assertSame($completion, $state->completion);
        self::assertSame([], $state->memory->cells);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerUnchangedCompletions(): iterable
    {
        yield 'try' => ['try', 'throw', 'throw'];
        yield 'catch' => ['catch', 'throw', 'throw'];
        yield 'saved return' => ['finally', 'return', 'throw'];
        yield 'saved jump' => ['finally', 'jump', 'throw'];
        yield 'new return' => ['finally', 'throw', 'return'];
        yield 'new jump' => ['finally', 'throw', 'jump'];
    }

    public function testAppendPreservesAnExistingChainAndRejectsCycles(): void
    {
        $program = SolverFixture::context()->program;
        $state = new State();
        $unwinding = new Unwinding($program);
        $a = $unwinding->capture($state, new Term('throwable', 'RuntimeException'));
        $b = $unwinding->capture($state, new Term('throwable', 'LogicException'));
        $c = $unwinding->capture($state, new Term('throwable', 'Error'));
        $chain = new ExceptionChain($program);
        $chain->append($state, $a, $b);
        $chain->append($state, $a, $c);
        $chain->append($state, $c, $a);
        $chain->append($state, $a, $b);
        self::assertSame($b, $state->memory->read(new Location('object:' . $a->literal, ['Exception::previous'])));
        self::assertSame($c, $state->memory->read(new Location('object:' . $b->literal, ['Exception::previous'])));
        self::assertNull($state->memory->read(new Location('object:' . $c->literal, ['Error::previous']))->native());
    }

    public function testAncestorsTerminatesOnCyclesAndUnknownObjects(): void
    {
        $state = new State();
        $a = new Term('object', 'a', attributes: ['class' => 'Exception']);
        $b = new Term('object', 'b', attributes: ['class' => 'Error']);
        $state->memory->write(new Location('object:a', ['Exception::previous']), $b);
        $state->memory->write(new Location('object:b', ['Error::previous']), $a);
        $chain = new ExceptionChain(SolverFixture::context()->program);
        self::assertSame(['a' => true, 'b' => true], $chain->ancestors($state, $a));
        self::assertSame(['unknown' => true], $chain->ancestors($state, new Term('object', 'unknown')));
        self::assertSame([], $chain->ancestors($state, Term::constant(null)));
    }

    public function testLocationResolvesBothNativeFamiliesAndSourceInheritance(): void
    {
        $chain = new ExceptionChain(SolverFixture::context('<?php class Problem extends RuntimeException{}')->program);
        self::assertEquals(new Location('object:a', ['Exception::previous']), $chain->location(new Term('object', 'a', attributes: ['class' => 'Problem'])));
        self::assertEquals(new Location('object:b', ['Error::previous']), $chain->location(new Term('object', 'b', attributes: ['class' => 'Error'])));
        self::assertNull($chain->location(new Term('object', 'unknown')));
    }
}
