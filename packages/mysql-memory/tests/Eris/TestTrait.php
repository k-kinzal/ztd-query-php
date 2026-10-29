<?php

declare(strict_types=1);

namespace Tests\Eris;

use Eris\Quantifier\ForAll;

/**
 * Keeps Eris's generation, attributes, seeds, trial counts and failure reporting,
 * replacing only the eager shrinker factory for composed semantic workloads.
 */
trait TestTrait
{
    use \Eris\TestTrait;

    /** @var list<ForAll> */
    private $quantifiers = [];
    /** @var array<int, \Eris\Listener> */
    private $listeners = [];
    /** @var list<\Eris\Quantifier\TerminationCondition> */
    private $terminationConditions = [];
    /** @var int|null */
    protected $shrinkingTimeLimit;

    /**
     * Starts the standard Eris quantifier with a streaming candidate consumer.
     */
    public function forAll(): ForAll
    {
        $this->randRange->seed($this->seed);
        $quantifier = new ForAll(func_get_args(), $this->iterations, new ShrinkerFactory(['timeLimit' => $this->shrinkingTimeLimit]), $this->shrinkerFactoryMethod, $this->randRange);
        foreach ($this->listeners as $listener) {
            $quantifier->hook($listener);
        }
        foreach ($this->terminationConditions as $condition) {
            $quantifier->stopOn($condition);
        }
        $this->quantifiers[] = $quantifier;
        return $quantifier;
    }
}
