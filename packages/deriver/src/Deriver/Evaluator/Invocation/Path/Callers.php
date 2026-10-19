<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Invocation\Path;

use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Discovers potential caller edges before deriving any invocation inputs.
 * @visibility parent
 */
final class Callers
{
    /**
     * @var array<string, list<array{BodyIndex, Instruction}>>
     */
    private array $cache = [];

    /**
     * Shares captured dependency indexes with ordinary derivation.
     */
    public function __construct(private readonly DefinitionIndex $index)
    {
    }

    /**
     * Filters named parents before lowering unrelated callers.
     * @return list<array{BodyIndex, Instruction}>
     */
    public function sites(string $callee, ?string $owner): array
    {
        $key = serialize([$callee, $owner]);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $selector = str_contains($callee, '::') && !str_ends_with(strtolower($callee), '::__construct') ? explode('::', $callee)[1] : $callee;
        $owners = $owner === null ? array_unique([...$this->index->program->callOwners($selector), ...$this->index->program->callOwners('')]) : [$owner];
        $sites = [];
        foreach ($owners as $name) {
            $graph = $this->index->graph($name);
            if ($graph !== null) {
                array_push($sites, ...$this->within($graph, $callee));
            }
        }
        return $this->cache[$key] = $sites;
    }

    /**
     * Retains dynamic dispatch candidates for the normal evaluator to resolve.
     * @return list<array{BodyIndex, Instruction}>
     */
    public function within(BodyIndex $graph, string $callee): array
    {
        $sites = [];
        $identity = new CallableIdentity();
        $method = str_contains($callee, '::') ? explode('::', $callee)[1] : null;
        foreach ($graph->definitions as $call) {
            if (!in_array($call->operation, ['invoke', 'invoke-method', 'invoke-static', 'new'], true)) {
                continue;
            }
            $target = $this->index->target($graph, $call);
            $dynamic = $call->operation === 'invoke' && $this->index->literal($graph, $call->operands[0]) === '';
            $name = $this->index->literal($graph, $call->operands[1] ?? '');
            $methodCall = $method !== null && in_array($call->operation, ['invoke-method', 'invoke-static'], true) && ($name === '' || strcasecmp($name, $method) === 0) && $this->index->compatible($target, $callee);
            if ($identity->key($target) === $identity->key($callee) || $dynamic || $methodCall) {
                $sites[] = [$graph, $call];
            }
        }
        return $sites;
    }
}
