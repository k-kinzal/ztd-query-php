<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Php\NodeText;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\TypeReader;
use SqlCatalog\Text\Origin;

/**
 * Runs the analysis over one parsed file, body by body.
 *
 * Every function body is walked on its own, with its parameters standing for
 * whatever a caller may pass. Bodies reached by following a call are walked
 * again with the caller's values, which is what resolves a statement the code
 * assembles across several methods.
 *
 * @visibility root
 */
final class Interpreter
{
    private ProgramIndex $index;

    /**
     * @var list<SinkSpec>
     */
    private array $sinks;

    private EvaluationBudget $budget;

    private NodeFinder $finder;

    private NodeText $text;

    private TypeReader $types;

    /**
     * @param ProgramIndex $index The declarations of the whole analyzed source tree
     * @param list<SinkSpec> $sinks The database calls the enabled extensions recognise
     * @param EvaluationBudget|null $budget How much work one file may cost
     */
    public function __construct(ProgramIndex $index, array $sinks, ?EvaluationBudget $budget = null)
    {
        $this->index = $index;
        $this->sinks = $sinks;
        $this->budget = $budget ?? new EvaluationBudget();
        $this->finder = new NodeFinder();
        $this->text = new NodeText();
        $this->types = new TypeReader();
    }

    /**
     * Every statement the file can issue.
     *
     * @return list<QueryRecord>
     */
    public function analyze(ParsedFile $file): array
    {
        $this->budget->reset();
        $recorder = new StatementRecorder();
        $expressions = $this->evaluatorFor($recorder);

        $expressions->bodies()->walk(
            $file->statements,
            new Environment(),
            new FunctionScope($file->path, FunctionScope::MAIN),
        );

        foreach ($this->finder->findInstanceOf($file->statements, FunctionLike::class) as $body) {
            $this->analyzeBody($body, $file, $expressions);
        }

        return $recorder->records();
    }

    /**
     * Walks one function body with its parameters left open.
     */
    public function analyzeBody(FunctionLike $body, ParsedFile $file, ExpressionEvaluator $expressions): void
    {
        $statements = $body->getStmts();
        if ($statements === null) {
            return;
        }

        $className = $this->enclosingClass($body);
        $environment = new Environment();
        foreach ($body->getParams() as $parameter) {
            if ($parameter->var instanceof Node\Expr\Variable && is_string($parameter->var->name)) {
                $environment->write(
                    $parameter->var->name,
                    Domain::opaque($this->types->read($parameter->type), Origin::Parameter, '$' . $parameter->var->name),
                );
            }
        }

        $expressions->bodies()->walk(
            $statements,
            $environment,
            new FunctionScope($file->path, $this->nameOf($body, $className), $className),
        );
    }

    /**
     * The evaluator wired to record into the given recorder.
     */
    public function evaluatorFor(StatementRecorder $recorder): ExpressionEvaluator
    {
        $external = new ExternalInput();

        return new ExpressionEvaluator(
            new ReferenceEvaluator($this->index, $external, $this->text),
            new CallEvaluator(
                $this->index,
                new SinkMatcher($this->sinks, $this->index),
                $recorder,
                new BuiltinCallModel(),
                $external,
                $this->budget,
                $this->text,
            ),
            $this->budget,
            $this->text,
        );
    }

    /**
     * The class a body belongs to, or null when it belongs to none.
     */
    public function enclosingClass(FunctionLike $body): ?string
    {
        $node = $body->getAttribute('parent');
        while ($node instanceof Node) {
            if ($node instanceof Stmt\ClassLike) {
                return $node->namespacedName?->toString() ?? $node->name?->toString();
            }
            $node = $node->getAttribute('parent');
        }

        return null;
    }

    /**
     * The name findings use for a body.
     */
    public function nameOf(FunctionLike $body, ?string $className): string
    {
        if ($body instanceof Stmt\ClassMethod) {
            return ($className ?? '') . '::' . $body->name->toString();
        }
        if ($body instanceof Stmt\Function_) {
            return $body->namespacedName?->toString() ?? $body->name->toString();
        }

        return ($className ?? FunctionScope::MAIN) . '::{closure}';
    }
}
