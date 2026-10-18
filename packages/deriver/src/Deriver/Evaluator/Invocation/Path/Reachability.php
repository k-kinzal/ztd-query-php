<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Invocation\Path;

use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Request\CallPath;
use Deriver\Deriver\Evaluator\Scope\Context;
use SplStack;

/**
 * Rejects ancestry cycles with no structural route to the remaining named callers.
 * @visibility parent
 */
final class Reachability
{
    /**
     * @var SplStack<array{BodyIndex, int}>
     */
    private SplStack $pending;

    /**
     * Shares discovery and the query work budget without evaluating application values.
     */
    public function __construct(private readonly Callers $callers, private readonly CallPath $path, private readonly Context $context)
    {
    }

    /**
     * An exhausted discovery budget keeps the route possible for residual handling.
     */
    public function allows(BodyIndex $graph, int $position): bool
    {
        $this->pending = new SplStack();
        $this->pending->push([$graph, $position]);
        $seen = [];
        while (!$this->pending->isEmpty()) {
            [$current, $offset] = $this->pending->pop();
            if ($offset < 0 || $this->context->work() !== null) {
                return true;
            }
            $key = $current->body->symbol . ':' . $offset;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $step = $this->path->callers[$offset];
            foreach ($this->callers->sites($current->body->symbol, $step === '...' ? null : $step) as [$caller]) {
                foreach ($this->path->advance($caller->body->symbol, $offset) as $remaining) {
                    $this->pending->push([$caller, $remaining]);
                }
            }
        }
        return false;
    }
}
