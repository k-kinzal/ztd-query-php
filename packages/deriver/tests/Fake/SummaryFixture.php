<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Api\Query\Budget;
use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\Solver\Call\ArgumentBinding;
use Deriver\Internal\Solver\Context;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use LogicException;

/**
 * Supplies fully evaluated demand graphs for fixed-point and replay contract tests.
 * @visibility root
 */
final class SummaryFixture
{
    /**
     * Resolves a required captured source body in a small test world.
     * @param Context $context Fixture world
     * @param string $symbol Required callable
     * @return CallableIR Source graph
     * @throws LogicException If the fixture omits the requested symbol
     */
    public static function body(Context $context, string $symbol = 'target'): CallableIR
    {
        return $context->program->callable($symbol) ?? throw new LogicException('Missing fixture callable.');
    }

    /**
     * Solves symbolic source entries and exposes their internal dependency registry.
     * @param string $source Captured PHP program
     * @param Budget $budget Reproducible logical limits
     * @return Context Completed query context
     */
    public static function run(string $source, Budget $budget = new Budget()): Context
    {
        $context = SolverFixture::context($source, $budget);
        $body = self::body($context);
        $machine = new Machine($context);
        foreach ((new ArgumentBinding($machine))->bind($body, new State(), [], symbolic: true) as $entry) {
            $machine->run($body, $entry);
        }
        return $context;
    }
    /**
     * Builds a declared dependency graph independently of solver discovery.
     * @param array<string, list<string>> $edges Dependents mapped to their dependencies
     * @return \Deriver\Internal\Solver\Demand\Table Registered graph
     */
    public static function graph(array $edges): \Deriver\Internal\Solver\Demand\Table
    {
        $context = SolverFixture::context();
        $body = self::body($context);
        $table = $context->summaries;
        $keys = [];
        foreach ($edges as $name => $_) {
            $keys[$name] = new \Deriver\Internal\Solver\Demand\Key('test', $name, 'entry', 'value', '', '', '', '');
            $table->register($keys[$name], $body, new State());
        }
        foreach ($edges as $name => $dependencies) {
            $table->stack = [$keys[$name]->id()];
            foreach ($dependencies as $dependency) {
                $table->register($keys[$dependency], $body, new State());
            }
        }
        $table->stack = [];
        return $table;
    }
}
