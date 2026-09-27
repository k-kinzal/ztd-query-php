<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionChain;
use Deriver\Evaluation\Control\Handler;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Memory\Memory;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\ConstantSignatures;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Arrays;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(ExceptionChain::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(Handler::class)]
#[UsesClass(Resources::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(State::class)]
#[UsesClass(Location::class)]
#[UsesClass(Memory::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Operations::class)]
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
