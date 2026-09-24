<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\FunctionModel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\BuiltinCallModel;
use SqlCatalog\Analysis\FunctionModel\Registry;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;

#[CoversClass(Registry::class)]
#[UsesClass(BuiltinCallModel::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
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
#[UsesClass(\SqlCatalog\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Extension\Model\CallContext::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
final class RegistryTest extends TestCase
{
    public function testWithBuiltinsRetainsStandardModels(): void
    {
        $models = Registry::withBuiltins();
        self::assertSame('USERS', $models->evaluate('strtoupper', [Domain::literal('users')])?->soleLiteral()?->value);
        self::assertNull($models->evaluate('array_fill', []));
    }

    public function testRegisterOverridesAndCanDeferToEarlierModels(): void
    {
        $models = Registry::withBuiltins();
        $models->register('STRTOUPPER', static fn (array $arguments): ?Domain => ($arguments[0] ?? Domain::unknown())->soleLiteral()?->value === 'special' ? Domain::literal('override') : null);
        self::assertSame('override', $models->evaluate('strtoupper', [Domain::literal('special')])?->soleLiteral()?->value);
        self::assertSame('OTHER', $models->evaluate('strtoupper', [Domain::literal('other')])?->soleLiteral()?->value);
    }

    public function testSupportsKeepsNamespacedFunctionsSeparate(): void
    {
        $models = new Registry();
        $models->register('\\App\\table', static fn (array $arguments): Domain => Domain::literal('users'));
        self::assertTrue($models->supports('app\\TABLE'));
        self::assertFalse($models->supports('table'));
        self::assertFalse($models->supports('Other\\table'));
    }

    public function testEvaluateReturnsNullWhenEveryModelDeclines(): void
    {
        $models = new Registry();
        $models->register('table', static fn (array $arguments): ?Domain => null);
        self::assertNull($models->evaluate('table', []));
        self::assertNull($models->evaluate('unknown', []));
    }

    public function testNormalizeKeepsTheNamespace(): void
    {
        self::assertSame('app\\implode', (new Registry())->normalize('\\App\\IMPlODE'));
    }
    public function testRegisterOverridesTheBuiltinArrayFill(): void
    {
        $models = Registry::withBuiltins();
        $models->register('array_fill', static fn (array $arguments): Domain => Domain::literal('custom'));
        self::assertSame('custom', $models->evaluate('array_fill', [Domain::literal(0), Domain::literal(3), Domain::literal('?')])?->soleLiteral()?->value);
    }
    public function testEvaluateCallUsesTheNewestHandlingFunctionAndPreservesItsDomain(): void
    {
        $value = Domain::unknown('unresolved fragment')->concat(Domain::literal(' WHERE id = ?'));
        $context = new \SqlCatalog\Extension\Model\CallContext(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('sql')), [], new \SqlCatalog\Evaluation\Environment(), new \SqlCatalog\Analysis\FunctionScope('query.php'), (new \SqlCatalog\Analysis\Interpreter(new \SqlCatalog\Php\ProgramIndex(), []))->evaluatorFor());
        $models = new Registry();
        $models->registerCall(static fn (\SqlCatalog\Extension\Model\CallContext $call): Domain => Domain::literal('wrong'));
        $models->registerCall(static fn (\SqlCatalog\Extension\Model\CallContext $call): Domain => $value);
        $models->registerCall(static fn (\SqlCatalog\Extension\Model\CallContext $call): ?Domain => null);
        self::assertSame($value, $models->evaluateCall($context));
        self::assertNull((new Registry())->evaluateCall($context));
    }

    public function testRegisterCallKeepsNamedFunctionModelsAvailable(): void
    {
        $models = Registry::withBuiltins();
        $models->registerCall(static fn (\SqlCatalog\Extension\Model\CallContext $call): ?Domain => null);
        self::assertSame('USERS', $models->evaluate('strtoupper', [Domain::literal('users')])?->soleLiteral()?->value);
    }

}
