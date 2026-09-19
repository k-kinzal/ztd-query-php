<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\PathSet;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Php\NodeText;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\TypeReader;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

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

    private SinkFinder $finder2;

    /**
     * @var array<string, true>|null
     */
    private ?array $reaching = null;

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
        $this->finder2 = new SinkFinder();
    }

    /**
     * Every statement the file can issue.
     *
     * @return list<QueryRecord>
     */
    public function analyze(ParsedFile $file): array
    {
        $recorder = new StatementRecorder();
        $expressions = $this->evaluatorFor($recorder);

        if ($this->reaches($file->path . ':main')) {
            $this->budget->reset();
            $expressions->bodies()->walk(
                $file->statements,
                new PathSet(),
                new FunctionScope($file->path, FunctionScope::MAIN, null, [FunctionScope::MAIN]),
            );
        }

        foreach ($this->finder->findInstanceOf($file->statements, FunctionLike::class) as $body) {
            if ($this->reaches($file->path . ':' . $body->getStartFilePos())) {
                $this->analyzeBody($body, $file, $expressions);
            }
        }
        $this->recordUnreached($file, $recorder);

        return $recorder->records();
    }

    /**
     * Narrows the walk to the bodies that can reach a database call.
     *
     * @param array<string, true> $reaching
     */
    public function restrictTo(array $reaching): void
    {
        $this->reaching = $reaching;
    }

    /**
     * Whether a body is one the walk has anything to learn from.
     */
    public function reaches(string $key): bool
    {
        return $this->reaching === null || isset($this->reaching[$key]);
    }

    /**
     * Records the database calls the walk never reached.
     *
     * A call the walk did not visit is a gap in the analysis, not an absence in
     * the program. Reporting it with its statement left open keeps the two
     * apart, so that stopping early is never read as having found nothing.
     */
    public function recordUnreached(ParsedFile $file, StatementRecorder $recorder): void
    {
        foreach ($this->finder2->find($file, $this->sinks) as $call) {
            $siteKey = $file->path . ':' . $call->getStartFilePos();
            if ($recorder->hasVisited($siteKey)) {
                continue;
            }
            $body = $this->finder2->enclosingBody($call);
            $className = $body === null ? null : $this->enclosingClass($body);
            $recorder->record(
                new CallSite(
                    $file->path,
                    $call->getStartLine(),
                    $body === null ? FunctionScope::MAIN : $this->nameOf($body, $className),
                    'unreached',
                ),
                $siteKey,
                TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown(), 'call not reached')),
            );
        }
    }

    /**
     * Walks one function body with its parameters left open.
     *
     * Each body starts with the budget refilled. Spending one budget across a
     * whole file would let its first bodies use it up and leave the rest of the
     * file reported as unreached, which says more about the order the file is
     * written in than about the program.
     */
    public function analyzeBody(FunctionLike $body, ParsedFile $file, ExpressionEvaluator $expressions): void
    {
        $statements = $body->getStmts();
        if ($statements === null) {
            return;
        }

        $this->budget->reset();
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

        $name = $this->nameOf($body, $className);
        $expressions->bodies()->walk(
            $statements,
            PathSet::of($environment),
            new FunctionScope($file->path, $name, $className, [$name]),
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
