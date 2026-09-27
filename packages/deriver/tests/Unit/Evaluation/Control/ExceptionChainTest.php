<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\ControlFlow\ExceptionRegion;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Control\ExceptionChain;
use Deriver\Evaluation\Control\Handler;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(ExceptionChain::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(Handler::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(State::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
