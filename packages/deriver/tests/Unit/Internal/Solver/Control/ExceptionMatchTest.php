<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Control;

use Deriver\Internal\Solver\Control\ExceptionMatch;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(ExceptionMatch::class)]
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
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(Term::class)]
#[Small]
final class ExceptionMatchTest extends TestCase
{
    /**
     * @param string $bound Input upper bound
     * @param list<string> $catches Catch union
     * @param bool $matched Whether the catch is feasible
     * @param bool $remaining Whether an uncaught subset is feasible
     */
    #[DataProvider('providerBounds')]
    public function testPartitionRetainsExactlyTheFeasibleSubsets(string $bound, array $catches, bool $matched, bool $remaining): void
    {
        $types = new ExceptionMatch(SolverFixture::context('<?php interface Tagged{}final class Box{}class ExternalProblem extends Missing{}class Problem extends RuntimeException implements Tagged{}')->program);
        [$caught, $rest] = $types->partition(Term::parameter('input', $bound), $catches);
        self::assertSame($matched, $caught !== null);
        self::assertSame($remaining, $rest !== null);
    }

    /**
     * @return iterable<string, array{string, list<string>, bool, bool}>
     */
    public static function providerBounds(): iterable
    {
        yield 'definite root' => ['RuntimeException', ['Exception'], true, false];
        yield 'disjoint roots' => ['Exception', ['Error'], false, true];
        yield 'possible subclass' => ['Exception', ['RuntimeException'], true, true];
        yield 'all throwable roots' => ['Throwable', ['Exception', 'Error'], true, false];
        yield 'one throwable root' => ['Throwable', ['Error'], true, true];
        yield 'union fully caught' => ['RuntimeException|TypeError', ['Exception', 'Error'], true, false];
        yield 'mixed invalid subset' => ['mixed', ['Throwable'], true, true];
        yield 'object invalid subset' => ['object', ['Throwable'], true, true];
        yield 'int invalid' => ['int', ['Throwable'], false, true];
        yield 'primitive union invalid' => ['null|array|string', ['Throwable'], false, true];
        yield 'closure bound invalid' => ['Closure', ['Throwable'], false, true];
        yield 'ordinary class invalid' => ['Box', ['Throwable'], false, true];
        yield 'implemented interface' => ['Problem', ['Tagged'], true, false];
        yield 'interface may be throwable' => ['Tagged', ['Throwable'], true, true];
        yield 'intersection root' => ['Tagged&RuntimeException', ['Exception'], true, false];
        yield 'intersection subtype' => ['Tagged&Exception', ['RuntimeException'], true, true];
        yield 'intersection disjoint' => ['Tagged&Exception', ['Error'], false, true];
        yield 'dnf partly caught' => ['(Tagged&Exception)|Error', ['Tagged'], true, true];
        yield 'missing parent' => ['ExternalProblem', ['Throwable'], true, true];
        yield 'unknown hierarchy' => ['VendorProblem', ['Exception'], true, true];
        yield 'invokable exception possible' => ['callable', ['Throwable'], true, true];
    }

    public function testPartitionPreservesExclusionsAcrossRepeatedCatchClauses(): void
    {
        $types = new ExceptionMatch(SolverFixture::context()->program);
        $input = Term::parameter('input', 'Exception');
        [$caught, $remaining] = $types->partition($input, ['RuntimeException']);
        self::assertNotNull($caught);
        self::assertSame('RuntimeException', $caught->attributes['type']);
        self::assertSame($input, $caught->operands[0]);
        self::assertNotNull($remaining);
        [$shadowed, $rest] = $types->partition($remaining, ['OverflowException']);
        self::assertNull($shadowed);
        self::assertSame($remaining, $rest);
        self::assertSame([$remaining, null], $types->partition($remaining, ['Exception']));
    }

    /**
     * @param Term $value Evaluated throw operand
     * @param bool $valid Whether the exact value is a Throwable
     */
    #[DataProvider('providerExactValues')]
    public function testExactRecognizesRuntimeValuesAndClassIdentity(Term $value, bool $valid): void
    {
        $types = new ExceptionMatch(SolverFixture::context()->program);
        self::assertSame($valid ? [$value, null] : [null, $value], $types->partition($value, ['Throwable']));
    }

    /**
     * @return iterable<string, array{Term, bool}>
     */
    public static function providerExactValues(): iterable
    {
        yield 'integer' => [Term::constant(5), false];
        yield 'null' => [Term::constant(null), false];
        yield 'array' => [Term::array([]), false];
        yield 'closure' => [new Term('closure', 'body'), false];
        yield 'enum' => [new Term('enum', 'Mode::Ready'), false];
        yield 'plain object' => [new Term('object', 'box', attributes: ['class' => 'stdClass']), false];
        yield 'native error' => [new Term('throwable', 'TypeError'), true];
        yield 'allocated exception' => [new Term('object', 'exception', attributes: ['class' => 'RuntimeException']), true];
        yield 'uncertain throwable' => [new Term('throwable', 'Throwable', attributes: ['uncertain' => true]), true];
    }

    public function testRefinePreservesConfidentialityAndDeduplicatesBounds(): void
    {
        $input = new Term('parameter', 'secret', attributes: ['type' => 'Throwable'], secret: true);
        $refined = (new ExceptionMatch(SolverFixture::context()->program))->refine($input, ['Error', 'Error'], ['', 'TypeError', 'TypeError']);
        self::assertTrue($refined->isSecret());
        self::assertSame(['type' => 'Error', 'excluded-types' => 'TypeError'], $refined->attributes);
        self::assertSame($input, $refined->operands[0]);
    }

    public function testArmsExpandsThrowableRootsAndPreservesIntersections(): void
    {
        $match = new ExceptionMatch(SolverFixture::context()->program);
        self::assertSame(['Exception', 'Error', 'Tagged&Exception', 'null'], $match->arms('throwable|(Tagged&Exception)|null'));
    }

    public function testIntersectionRetainsIndependentInterfaceBounds(): void
    {
        $match = new ExceptionMatch(SolverFixture::context('<?php interface Tagged{}')->program);
        self::assertSame('Tagged&RuntimeException', $match->intersection('Tagged&Exception', 'RuntimeException'));
        self::assertNull($match->intersection('RuntimeException', 'LogicException'));
        self::assertSame('RuntimeException', $match->intersection('mixed', 'RuntimeException'));
    }

    public function testImpliesDistinguishesNecessaryAndPossibleSubclassRelations(): void
    {
        $match = new ExceptionMatch(SolverFixture::context()->program);
        self::assertTrue($match->implies('Tagged&RuntimeException', 'Throwable'));
        self::assertFalse($match->implies('Exception', 'RuntimeException'));
    }

    public function testOverlapsRetainsUnknownInterfacesAndRejectsSiblingClasses(): void
    {
        $match = new ExceptionMatch(SolverFixture::context('<?php interface Tagged{}')->program);
        self::assertTrue($match->overlaps('Tagged', 'Exception'));
        self::assertTrue($match->overlaps('Exception', 'RuntimeException'));
        self::assertFalse($match->overlaps('RuntimeException', 'LogicException'));
        self::assertFalse($match->overlaps('int', 'Exception'));
    }

    public function testConcreteClassSeparatesSourceAndNativeClassesFromInterfaces(): void
    {
        $match = new ExceptionMatch(SolverFixture::context('<?php interface Tagged{}class Box{}')->program);
        self::assertTrue($match->concreteClass('Box'));
        self::assertTrue($match->concreteClass('Exception'));
        self::assertFalse($match->concreteClass('Throwable'));
        self::assertFalse($match->concreteClass('Tagged'));
        self::assertFalse($match->concreteClass('Missing'));
    }

    public function testExcludedRemovesAllSubclassesOfAnEarlierCatch(): void
    {
        $match = new ExceptionMatch(SolverFixture::context()->program);
        self::assertTrue($match->excluded('OverflowException', ['', 'RuntimeException']));
        self::assertFalse($match->excluded('LogicException', ['', 'RuntimeException']));
    }

    public function testPartitionRetainsUnknownParentsForAllocatedObjects(): void
    {
        $match = new ExceptionMatch(SolverFixture::context('<?php class Problem extends Missing{}')->program);
        [$caught, $remaining] = $match->partition(new Term('object', 'problem', attributes: ['class' => 'Problem']), ['Throwable']);
        self::assertNotNull($caught);
        self::assertNotNull($remaining);
        self::assertSame('Problem&Exception|Problem&Error', $caught->attributes['type']);
    }
}
