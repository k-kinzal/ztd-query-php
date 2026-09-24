<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation\Objects;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\Objects\BranchEffects;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(BranchEffects::class)]
#[UsesClass(Domain::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(PatternTerm::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextGeneralization::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(Environment::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
final class BranchEffectsTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTruth')]
    public function testTruthUsesPhpScalarTruthiness(string|int|bool|null $value, bool $expected): void
    {
        self::assertSame($expected, (new BranchEffects())->truth(Domain::literal($value)));
    }

    /**
     * @return iterable<array{string|int|bool|null, bool}>
     */
    public static function providerTruth(): iterable
    {
        yield [true, true];
        yield [false, false];
        yield [null, false];
        yield [0, false];
        yield [1, true];
        yield ['', false];
        yield ['0', false];
        yield ['yes', true];
    }

    public function testTruthKeepsUnknownValuesOpenAndRecognizesTrackedObjects(): void
    {
        self::assertNull((new BranchEffects())->truth(Domain::unknown()));
        self::assertTrue((new BranchEffects())->truth(Domain::of(new ObjectTerm('Builder'))));
    }

    public function testCoalesceDoesNotRunTheRightSideWhenTheLeftIsNonNull(): void
    {
        $env = new Environment(['x' => Domain::literal(0)]);
        $expr = new \PhpParser\Node\Expr\BinaryOp\Coalesce(new \PhpParser\Node\Scalar\String_('left'), new \PhpParser\Node\Expr\Assign(new \PhpParser\Node\Expr\Variable('x'), new \PhpParser\Node\Scalar\Int_(2)));
        $value = (new BranchEffects())->coalesce($expr, $env, new FunctionScope('query.php'), (new Interpreter(new ProgramIndex(), []))->evaluatorFor());
        self::assertSame('left', $value->soleLiteral()?->value);
        self::assertSame(0, $env->read('x')->soleLiteral()?->value);
    }

    public function testCoalesceRunsTheRightSideForNullAndJoinsForUnknownValues(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $expr = new \PhpParser\Node\Expr\BinaryOp\Coalesce(new \PhpParser\Node\Expr\Variable('value'), new \PhpParser\Node\Expr\Assign(new \PhpParser\Node\Expr\Variable('x'), new \PhpParser\Node\Scalar\Int_(2)));
        $null = new Environment(['value' => Domain::literal(null), 'x' => Domain::literal(0)]);
        $unknown = new Environment(['value' => Domain::unknown(), 'x' => Domain::literal(0)]);
        $effects = new BranchEffects();
        self::assertSame(2, $effects->coalesce($expr, $null, new FunctionScope('query.php'), $expressions)->soleLiteral()?->value);
        self::assertSame(2, $null->read('x')->soleLiteral()?->value);
        $effects->coalesce($expr, $unknown, new FunctionScope('query.php'), $expressions);
        self::assertCount(2, $unknown->read('x')->terms);
    }

    /**
     * @param class-string<\PhpParser\Node\Expr\BinaryOp> $operator
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerShortCircuits')]
    public function testShortCircuitHonorsResolvedLeftOperands(string $operator, bool $left, int $expected): void
    {
        $env = new Environment(['left' => Domain::literal($left), 'x' => Domain::literal(0)]);
        $expr = new $operator(new \PhpParser\Node\Expr\Variable('left'), new \PhpParser\Node\Expr\Assign(new \PhpParser\Node\Expr\Variable('x'), new \PhpParser\Node\Scalar\Int_(2)));
        (new BranchEffects())->shortCircuit($expr, $env, new FunctionScope('query.php'), (new Interpreter(new ProgramIndex(), []))->evaluatorFor());
        self::assertSame($expected, $env->read('x')->soleLiteral()?->value);
    }

    /**
     * @return iterable<array{class-string<\PhpParser\Node\Expr\BinaryOp>, bool, int}>
     */
    public static function providerShortCircuits(): iterable
    {
        yield [\PhpParser\Node\Expr\BinaryOp\BooleanAnd::class, false, 0];
        yield [\PhpParser\Node\Expr\BinaryOp\BooleanAnd::class, true, 2];
        yield [\PhpParser\Node\Expr\BinaryOp\LogicalAnd::class, false, 0];
        yield [\PhpParser\Node\Expr\BinaryOp\LogicalAnd::class, true, 2];
        yield [\PhpParser\Node\Expr\BinaryOp\BooleanOr::class, false, 2];
        yield [\PhpParser\Node\Expr\BinaryOp\BooleanOr::class, true, 0];
        yield [\PhpParser\Node\Expr\BinaryOp\LogicalOr::class, false, 2];
        yield [\PhpParser\Node\Expr\BinaryOp\LogicalOr::class, true, 0];
    }

    public function testShortCircuitJoinsEffectsWhenTheConditionIsUnknown(): void
    {
        $env = new Environment(['left' => Domain::unknown(), 'x' => Domain::literal(0)]);
        $expr = new \PhpParser\Node\Expr\BinaryOp\BooleanAnd(new \PhpParser\Node\Expr\Variable('left'), new \PhpParser\Node\Expr\Assign(new \PhpParser\Node\Expr\Variable('x'), new \PhpParser\Node\Scalar\Int_(2)));
        $value = (new BranchEffects())->shortCircuit($expr, $env, new FunctionScope('query.php'), (new Interpreter(new ProgramIndex(), []))->evaluatorFor());
        self::assertSame(['bool'], $value->type()->names);
        self::assertCount(2, $env->read('x')->terms);
    }
}
