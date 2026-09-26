<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis\FunctionModel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\BuiltinCallModel;
use SqlCatalog\Core\Analysis\FunctionModel\Registry;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\LiteralTerm;

#[CoversClass(Registry::class)]
#[UsesClass(BuiltinCallModel::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Core\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\CallContext::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Core\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextPattern::class)]
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
        $context = new \SqlCatalog\Core\Extension\Model\CallContext(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('sql')), [], new \SqlCatalog\Core\Evaluation\Environment(), new \SqlCatalog\Core\Analysis\FunctionScope('query.php'), (new \SqlCatalog\Core\Analysis\Interpreter(new \SqlCatalog\Core\Php\ProgramIndex(), []))->evaluatorFor());
        $models = new Registry();
        $models->registerCall(static fn (\SqlCatalog\Core\Extension\Model\CallContext $call): Domain => Domain::literal('wrong'));
        $models->registerCall(static fn (\SqlCatalog\Core\Extension\Model\CallContext $call): Domain => $value);
        $models->registerCall(static fn (\SqlCatalog\Core\Extension\Model\CallContext $call): ?Domain => null);
        self::assertSame($value, $models->evaluateCall($context));
        self::assertNull((new Registry())->evaluateCall($context));
    }

    public function testRegisterCallKeepsNamedFunctionModelsAvailable(): void
    {
        $models = Registry::withBuiltins();
        $models->registerCall(static fn (\SqlCatalog\Core\Extension\Model\CallContext $call): ?Domain => null);
        self::assertSame('USERS', $models->evaluate('strtoupper', [Domain::literal('users')])?->soleLiteral()?->value);
    }

}
