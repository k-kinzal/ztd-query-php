<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use SqlCatalog\Core\Analysis\Derivation\CalleeReturns;
use SqlCatalog\Core\Analysis\Derivation\CallerIndex;
use SqlCatalog\Core\Analysis\Derivation\Callers;
use SqlCatalog\Core\Analysis\Derivation\Deriver;
use SqlCatalog\Core\Analysis\Derivation\EntryBinder;
use SqlCatalog\Core\Analysis\Derivation\FreeNames;
use SqlCatalog\Core\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Core\Analysis\Derivation\Objects\CallbackEffects;
use SqlCatalog\Core\Analysis\Derivation\Objects\ObjectEffects;
use SqlCatalog\Core\Analysis\Derivation\PropertyWrites;
use SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer;
use SqlCatalog\Core\Analysis\Derivation\SliceExecutor;
use SqlCatalog\Core\Analysis\Derivation\Solution;
use SqlCatalog\Core\Analysis\Derivation\SourceTree;
use SqlCatalog\Core\Analysis\FunctionModel\Registry;
use SqlCatalog\Core\Analysis\Model\ModelQueries;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Extension\Model\ModelContext;
use SqlCatalog\Core\Extension\Model\ModelProviderInterface;
use SqlCatalog\Core\Extension\Model\ModelSet;
use SqlCatalog\Core\Extension\SinkRole;
use SqlCatalog\Core\Extension\SinkSpec;
use SqlCatalog\Core\Php\DeclaredGlobals;
use SqlCatalog\Core\Php\NodeText;
use SqlCatalog\Core\Php\ParsedFile;
use SqlCatalog\Core\Php\ProgramIndex;
use SqlCatalog\Core\Php\TypeReader;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

/**
 * Reads the statements a source tree can issue, starting from every call that issues one.
 *
 * Every call written the way a database call is written is a starting point.
 * What it is called on is worked out first, which decides whether it is a
 * database call at all; then what its arguments can be is worked out from the
 * call backwards. Each way the arguments can be is one statement at the call,
 * and a value bound later, by an `execute()` or a `bindValue()`, is attached to
 * the statement the handle it is called on came from.
 *
 * @visibility root
 */
final class Interpreter
{
    private Registry $functions;

    private ProgramIndex $index;

    /**
     * @var list<SinkSpec>
     */
    private array $sinks;

    private EvaluationBudget $budget;

    private DeclaredGlobals $globals;

    private NodeText $text;

    private SinkFinder $finder;

    private ?ModelSet $models = null;

    /**
     * @param ProgramIndex $index The declarations of the whole analyzed source tree
     * @param list<SinkSpec> $sinks The database calls the enabled extensions recognise
     * @param EvaluationBudget|null $budget How much work one call may cost
     * @param DeclaredGlobals|null $globals What the global variables the source declares are known to hold
     * @param list<ModelProviderInterface> $modelProviders Enabled extension providers, instantiated for each derivation engine
     * @param Registry|null $functions The function interpretations shared by the derivation
     */
    public function __construct(
        ProgramIndex $index,
        array $sinks,
        ?EvaluationBudget $budget = null,
        ?DeclaredGlobals $globals = null,
        ?Registry $functions = null,
        private readonly ?string $dialect = null,
        private readonly array $modelProviders = [],
    ) {
        $this->functions = $functions ?? Registry::withBuiltins();
        $this->index = $index;
        $this->sinks = $sinks;
        $this->budget = $budget ?? new EvaluationBudget();
        $this->globals = $globals ?? new DeclaredGlobals();
        $this->text = new NodeText();
        $this->finder = new SinkFinder();
    }

    /**
     * Every statement the files can issue.
     *
     * @param list<ParsedFile> $files
     * @return list<QueryRecord>
     */
    public function analyze(array $files): array
    {
        $deriver = $this->deriverFor($files);
        $matcher = new SinkMatcher($this->sinks, $this->index, $this->models);
        $recorder = new StatementRecorder();
        $binder = new ValueBinder($recorder);
        $bindings = [];
        foreach ($files as $file) {
            foreach ($this->finder->findAll($file, $this->sinks) as $call) {
                $this->budget->reset();
                $bindings = array_merge($bindings, $this->visit($call, $deriver, $matcher, $recorder, $binder));
            }
        }
        foreach ($bindings as [$sink, $solution]) {
            $values = $solution->values;
            $receiver = array_shift($values) ?? Domain::unknown();
            $binder->bindValues($binder->openRecords($receiver), $sink, $values);
        }

        return $recorder->records();
    }

    /**
     * The expression evaluator the deriver for the given files reads values with.
     *
     * @param list<ParsedFile> $files
     */
    public function evaluatorFor(array $files = []): ExpressionEvaluator
    {
        return $this->deriverFor($files)->evaluator();
    }

    /**
     * The deriver wired over the given files.
     *
     * @param list<ParsedFile> $files
     */
    public function deriverFor(array $files): Deriver
    {
        $tree = new SourceTree($files);
        $effects = $this->modelProviders !== [];
        $names = new FreeNames($this->sinks, trackObjectEffects: $effects);
        $modified = new ModifiedNames($names, $effects ? new ObjectEffects($files) : null);
        $slicer = new BackwardSlicer($tree, $this->budget, $names, $modified);
        $executor = new SliceExecutor($this->globals, new TypeReader(), $modified, $this->text, $this->budget);
        $functions = clone $this->functions;
        $this->models = null;
        foreach ($this->modelProviders as $provider) {
            $models = $provider->models(new ModelContext($this->index, new CallbackEffects($slicer, $executor), $this->dialect));
            $this->models = $this->models?->merge($models) ?? $models;
        }
        foreach ($this->models->calls ?? [] as $model) {
            $functions->registerCall($model);
        }
        $external = new ExternalInput();
        $expressions = new ExpressionEvaluator(
            new ReferenceEvaluator($this->index, $external, $this->text),
            new CallEvaluator(
                $this->index,
                new SinkMatcher($this->sinks, $this->index, $this->models),
                $functions,
                $external,
                $this->text,
                new CalleeReturns($slicer, $executor, $this->budget, $names),
            ),
            $this->budget,
            $this->text,
        );

        return new Deriver(
            $tree,
            $slicer,
            $executor,
            $expressions,
            new EntryBinder(
                new Callers(new CallerIndex($files), $this->index, new SinkMatcher($this->sinks, $this->index, $this->models)),
                $expressions,
                $this->budget,
                $this->globals,
                new PropertyWrites($this->index, $modified),
            ),
            $this->budget,
            $names,
        );
    }

    /**
     * Records what one call issues, and hands back the values it binds for later.
     *
     * @return list<array{SinkSpec, Solution}>
     */
    public function visit(
        Expr\CallLike $call,
        Deriver $deriver,
        SinkMatcher $matcher,
        StatementRecorder $recorder,
        ValueBinder $binder,
    ): array {
        $scope = $deriver->scopeOf($call);
        $sink = $this->sinkOf($call, $deriver, $matcher, $scope);
        if ($sink === null) {
            if ($this->unidentified($call, $deriver, $matcher)) {
                $this->recordUnmatched($call, $scope, $recorder);
            }

            return [];
        }
        if ($sink->role === SinkRole::Compose) {
            return [];
        }
        $this->budget->reset();
        $arguments = $this->argumentsOf($call);
        if ($sink->role === SinkRole::Modelled) {
            $this->recordStatements($call, $sink, (new ModelQueries())->solve($call, $this->models->queries[$sink->model ?? ''] ?? null, $deriver), $scope, $recorder, $binder);

            return [];
        }
        if ($sink->role === SinkRole::Execute || $sink->role === SinkRole::Bind) {
            $receiver = $call instanceof Expr\MethodCall || $call instanceof Expr\NullsafeMethodCall ? $call->var : null;
            if ($receiver === null) {
                return [];
            }

            return array_map(
                static fn (Solution $solution): array => [$sink, $solution],
                $deriver->solve($call, array_merge([$receiver], $arguments)),
            );
        }
        if ($sink->sqlParameter === null || !isset($arguments[$sink->sqlParameter])) {
            return [];
        }
        $this->recordStatements($call, $sink, $deriver->solve($call, $arguments), $scope, $recorder, $binder);

        return [];
    }

    /**
     * The database call a call is, or null when it is not one the enabled extensions recognise.
     */
    public function sinkOf(Expr\CallLike $call, Deriver $deriver, SinkMatcher $matcher, FunctionScope $scope): ?SinkSpec
    {
        $name = $this->finder->nameOf($call);
        if ($name === null) {
            return null;
        }
        if ($call instanceof Expr\FuncCall) {
            return $matcher->matchFunction($name);
        }
        if ($call instanceof Expr\StaticCall) {
            $className = $call->class instanceof Node\Name ? $call->class->toString() : null;
            if ($className !== null && in_array(strtolower($className), ['self', 'static', 'parent'], true)) {
                $className = $scope->className ?? $className;
            }

            return $className === null ? null : $matcher->matchStatic($className, $name);
        }
        if ($call instanceof Expr\MethodCall || $call instanceof Expr\NullsafeMethodCall) {
            return $matcher->matchMethod($this->receiverOf($call, $deriver), $name);
        }

        return null;
    }

    /**
     * Everything the receiver of a method call can be.
     */
    public function receiverOf(Expr\MethodCall|Expr\NullsafeMethodCall $call, Deriver $deriver): Domain
    {
        $receiver = null;
        foreach ($deriver->solve($call, [$call->var]) as $solution) {
            $receiver = $receiver === null ? $solution->values[0] : $receiver->union($solution->values[0]);
        }

        return $receiver ?? Domain::unknown();
    }

    /**
     * Whether a call that carries statement text could not be told apart from a database call.
     *
     * A call on something whose class is known, and is not a database class,
     * is simply not a database call. A call on something whose class could not
     * be worked out might be one, and that is a gap worth reporting.
     */
    public function unidentified(Expr\CallLike $call, Deriver $deriver, SinkMatcher $matcher): bool
    {
        if (!$call instanceof Expr\MethodCall && !$call instanceof Expr\NullsafeMethodCall) {
            return false;
        }
        $name = $this->finder->nameOf($call);
        $carriesText = false;
        foreach ($matcher->byName(\SqlCatalog\Core\Extension\SinkCallKind::Method, $name ?? '') as $sink) {
            $carriesText = $carriesText || $sink->role === SinkRole::Query || $sink->role === SinkRole::Prepare || $sink->role === SinkRole::Modelled;
        }

        return $carriesText && $this->receiverOf($call, $deriver)->type()->classNames() === [];
    }

    /**
     * The expressions a call passes, in the order they are written.
     *
     * @return list<Expr>
     */
    public function argumentsOf(Expr\CallLike $call): array
    {
        if ($call->isFirstClassCallable()) {
            return [];
        }

        return array_values(array_map(static fn (Node\Arg $argument): Expr => $argument->value, $call->getArgs()));
    }

    /**
     * Records one statement for every way the call's statement text can be.
     *
     * @param list<Solution> $solutions
     */
    public function recordStatements(
        Expr\CallLike $call,
        SinkSpec $sink,
        array $solutions,
        FunctionScope $scope,
        StatementRecorder $recorder,
        ValueBinder $binder,
    ): void {
        $site = new CallSite($scope->file, $call->getStartLine(), $scope->function, $sink->id);
        $siteKey = $scope->file . ':' . $call->getStartFilePos() . ':' . $sink->id;
        $all = [];
        foreach ($solutions as $solution) {
            $sql = $sink->sqlParameter === null ? null : ($solution->values[$sink->sqlParameter] ?? null);
            if ($sql === null) {
                continue;
            }
            $records = [];
            foreach ($sql->patterns() as $pattern) {
                $records[] = $recorder->record($site, $siteKey, $pattern, $sink->kind, $sql->combined || $solution->combined, $solution->through, $solution->truncated);
            }
            $binder->bindValues($records, $sink, $solution->values);
            $all = array_merge($all, $records);
        }
        if ($all === []) {
            $all[] = $recorder->record($site, $siteKey, $this->unread($call), $sink->kind);
        }
        if ($sink->role === SinkRole::Prepare) {
            $recorder->filePrepared($siteKey, $all);
        }
    }

    /**
     * Records a call written as a database call on something that could not be identified.
     */
    public function recordUnmatched(Expr\CallLike $call, FunctionScope $scope, StatementRecorder $recorder): void
    {
        $recorder->record(
            new CallSite($scope->file, $call->getStartLine(), $scope->function, CallSite::UNMATCHED),
            $scope->file . ':' . $call->getStartFilePos(),
            $this->unread($call),
        );
    }

    /**
     * The statement of a call nothing was read from: one gap, quoting the call.
     */
    public function unread(Expr\CallLike $call): TextPattern
    {
        return TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown(), $this->text->render($call)));
    }
}
