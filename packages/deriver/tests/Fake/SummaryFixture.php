<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\ControlFlow\CallableGraph;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Query\Budget;
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
     * @return CallableGraph Source graph
     * @throws LogicException If the fixture omits the requested symbol
     */
    public static function body(Context $context, string $symbol = 'target'): CallableGraph
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
     * @return Table Registered graph
     */
    public static function graph(array $edges): Table
    {
        $context = SolverFixture::context();
        $body = self::body($context);
        $table = $context->summaries;
        $keys = [];
        foreach ($edges as $name => $_) {
            $keys[$name] = new Key('test', $name, 'entry', 'value', '', '', '', '');
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
