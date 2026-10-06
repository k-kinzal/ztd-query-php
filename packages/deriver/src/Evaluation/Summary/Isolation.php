<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Summary;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\Evaluation\Context;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Value\Term;
use WeakMap;

/**
 * Proves that a source call tree only mutates its own reference-free local storage.
 * @visibility root
 */
final class Isolation
{
    /**
     * @var WeakMap<Term, bool> Immutable input isolation facts
     */
    private WeakMap $values;

    /**
     * @param Context $context Frozen declaration and model world
     */
    public function __construct(public readonly Context $context)
    {
        $this->values = new WeakMap();
    }

    /**
     * Checks every reachable instruction, including mutually recursive source bodies.
     * @param CallableGraph $body Candidate graph
     * @param array<string, true> $seen Bodies already checked along this proof path
     * @return bool Whether local state can be safely rebased onto another invocation
     */
    public function callable(CallableGraph $body, array $seen = []): bool
    {
        $pending = [$body];
        $work = 0;
        while ($pending !== []) {
            $current = array_pop($pending);
            $key = (new CallableIdentity())->key($current->symbol);
            if (isset($seen[$key])) {
                continue;
            }
            if (!$this->context->available($current->source) || ++$work > $this->context->query->budget()->nodes || !$this->local($current)) {
                return false;
            }
            $seen[$key] = true;
            $dependencies = $this->dependencies($current, $work);
            if ($dependencies === null) {
                return false;
            }
            array_push($pending, ...$dependencies);
        }
        return true;
    }

    /**
     * Checks frame-level obligations before inspecting its instruction dependencies.
     * @param CallableGraph $body Candidate graph
     * @return bool Whether the frame has only isolated local bindings
     */
    public function local(CallableGraph $body): bool
    {
        $key = (new CallableIdentity())->key($body->symbol);
        if ($this->observed($body)) {
            return false;
        }
        if ($body->byReference || $body->className !== '' || $body->captures !== [] || str_starts_with($key, 'script:') || isset($this->context->models->models[$key])) {
            return false;
        }
        foreach ($body->parameters as $parameter) {
            if ($parameter->byReference) {
                return false;
            }
        }
        foreach ($body->regions as $region) {
            foreach ($region->catches as $catch) {
                if ($catch->variable !== '') {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Excludes every graph whose execution must produce an observation in this query or batch.
     * @param CallableGraph $body Candidate summary graph
     * @return bool Whether this graph must run to collect requested observations
     */
    public function observed(CallableGraph $body): bool
    {
        $key = (new CallableIdentity())->key($body->symbol);
        foreach ($this->context->batch->queries ?? [$this->context->query] as $query) {
            $owner = $query instanceof ValueQuery ? $query->expression->callable : (($query instanceof StateQuery || $query instanceof TupleQuery) ? $query->point->callable : null);
            if ($owner !== null && $key === (new CallableIdentity())->key($owner)) {
                return true;
            }
            if ($query instanceof ReturnQuery && ($query->scope()->mode !== 'symbolic' || $this->context->batch !== null) && $key === (new CallableIdentity())->key($query->symbol)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks local operations and follows every statically resolved source call.
     * @param CallableGraph $body Candidate graph
     * @param array<string, true> $seen Already checked source bodies
     * @return bool Whether all instructions preserve local isolation
     */
    public function instructions(CallableGraph $body, array $seen): bool
    {
        $work = 0;
        $dependencies = $this->dependencies($body, $work);
        if ($dependencies === null) {
            return false;
        }
        foreach ($dependencies as $dependency) {
            if (!$this->callable($dependency, $seen)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Collects only proven source callees within the finite optional proof budget.
     * @param CallableGraph $body Candidate graph
     * @param int $work Number of proof nodes visited across the complete call graph
     * @return list<CallableGraph>|null Required graphs, or an unproven isolation obligation
     */
    public function dependencies(CallableGraph $body, int &$work): ?array
    {
        $constants = [];
        $dependencies = [];
        foreach ($body->parameters as $parameter) {
            if ($parameter->default !== null) {
                $dependencies[] = $parameter->default;
            }
        }
        foreach ($body->blocks as $block) {
            foreach ($block->instructions as $instruction) {
                if (++$work > $this->context->query->budget()->nodes || $work % 256 === 0 && !$this->context->available($instruction->source)) {
                    return null;
                }
                if ($instruction->operation === 'constant' && is_string($instruction->constant?->literal)) {
                    $constants[$instruction->result] = $instruction->constant->literal;
                }
            }
        }
        foreach ($body->blocks as $block) {
            foreach ($block->instructions as $instruction) {
                if ($instruction->operation === 'invoke') {
                    $name = $constants[$instruction->operands[0] ?? ''] ?? '';
                    $callee = $this->context->program->callable($name);
                    if ($callee === null) {
                        return null;
                    }
                    $dependencies[] = $callee;
                } elseif (!in_array($instruction->operation, ['constant', 'copy', 'phi', 'binary', 'unary', 'cast', 'not-null', 'magic-constant', 'local', 'read', 'read-silent', 'write', 'compound', 'element-address', 'array-read', 'array', 'array-append', 'array-set', 'increment', 'unset', 'raise', 'enter-try', 'catch-bind', 'call-prepare', 'argument'], true)) {
                    return null;
                }
                if ($instruction->operation === 'local' && in_array($instruction->name, ['GLOBALS', '_GET', '_POST', '_COOKIE', '_SERVER', '_ENV', '_REQUEST', '_FILES', '_SESSION'], true)) {
                    return null;
                }
            }
        }
        return $dependencies;
    }

    /**
     * Rejects inputs with identities, references, or unbounded hidden heap structure.
     * @param Term $value Evaluated input
     * @return bool Whether copying the term cannot share mutable application state
     */
    public function value(Term $value): bool
    {
        /** @var WeakMap<Term, true> $visited */
        $visited = new WeakMap();
        $pending = [$value];
        while ($pending !== []) {
            $current = array_pop($pending);
            if (isset($visited[$current]) || ($this->values[$current] ?? null) === true) {
                continue;
            }
            if (($this->values[$current] ?? null) === false || count($visited) >= $this->context->query->budget()->nodes) {
                return false;
            }
            $visited[$current] = true;
            if (in_array($current->kind, ['constant', 'uninitialized', 'throwable'], true)) {
                continue;
            }
            $type = $current->attributes['type'] ?? '';
            if (in_array($current->kind, ['cell', 'object', 'closure', 'domain'], true) || ($current->kind === 'array' ? ($current->attributes['open'] ?? false) === true : !is_string($type) || array_diff(explode('|', $type), ['int', 'float', 'string', 'bool', 'true', 'false', 'null']) !== [])) {
                $this->values[$current] = false;
                return false;
            }
            array_push($pending, ...array_values($current->operands));
        }
        foreach ($visited as $checked => $_) {
            $this->values[$checked] = true;
        }
        return true;
    }
}
