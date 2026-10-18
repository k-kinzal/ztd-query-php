<?php

declare(strict_types=1);

namespace Deriver\Deriver\Identity;

use Deriver\Deriver\Evaluator\Request\Query;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\StateQuery;
use Deriver\Deriver\Evaluator\Request\TupleQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Lowerer\Graph\ExpressionRef;
use Deriver\Exception\InvalidInputException;

/**
 * Encodes every semantic query option, including scope and structural budgets.
 * @phpstan-import-type ScalarRecord from \Deriver\Deriver\Identity\ValueGraph
 * @phpstan-type EntryRecord array{symbol: string, arguments: list<array{name: ScalarRecord, value: string}>, receiver: string|null, properties: list<array{name: string, value: string}>, captures: list<array{name: string, value: string}>, symbolicArguments: bool}
 * @phpstan-type QueryRecord array{kind: string, symbol?: string, parameter?: string, expression?: \Deriver\Deriver\Lowerer\Graph\ExpressionRef, point?: \Deriver\Deriver\Lowerer\Graph\PointRef, variable?: string, projection?: \Deriver\Deriver\Evaluator\Projection, values?: object, scope: array{mode: string, entries: list<EntryRecord>, path?: array{callers: list<string>, atCall: bool}}, budget: \Deriver\Deriver\Evaluator\Request\Budget}
 * @visibility namespace
 * @visibility Deriver\Deriver\Publication
 * @visibility Deriver\Deriver\Execution
 * @visibility Deriver\Deriver
 * @visibility Deriver\Deriver
 */
final class QueryEncoding
{
    /**
     * Normalizes a query with entry values in the shared lossless graph.
     * @param Query $query Requested observation
     * @param ValueGraph $graph Canonical value encoding
     * @return QueryRecord Versioned query fields
     * @throws InvalidInputException If the query implementation is unsupported
     */
    public function record(Query $query, ValueGraph $graph): array
    {
        $record = match (true) {
            $query instanceof \Deriver\Deriver\Evaluator\Request\ParameterQuery => ['kind' => 'parameter', 'symbol' => $query->symbol, 'parameter' => $query->parameter],
            $query instanceof ReturnQuery => ['kind' => 'return', 'symbol' => $query->symbol],
            $query instanceof ValueQuery => ['kind' => 'value', 'expression' => $query->expression, 'projection' => $query->projection],
            $query instanceof StateQuery => ['kind' => 'state', 'point' => $query->point, 'variable' => $query->variable, 'projection' => $query->projection],
            $query instanceof TupleQuery => ['kind' => 'tuple', 'point' => $query->point, 'values' => (object) $query->values],
            default => throw new InvalidInputException('Unsupported query implementation.'),
        };
        $entries = [];
        foreach ($query->scope()->entries as $entry) {
            $arguments = [];
            foreach ($entry->arguments as $name => $value) {
                $arguments[] = ['name' => $graph->scalar($name), 'value' => $graph->add($value)];
            }
            $properties = [];
            foreach ($entry->properties as $name => $value) {
                $properties[] = ['name' => $name, 'value' => $graph->add($value)];
            }
            $captures = [];
            foreach ($entry->captures as $name => $value) {
                $captures[] = ['name' => $name, 'value' => $graph->add($value)];
            }
            $entries[] = ['symbol' => $entry->symbol, 'arguments' => $arguments, 'receiver' => $entry->receiver === null ? null : $graph->add($entry->receiver), 'properties' => $properties, 'captures' => $captures, 'symbolicArguments' => $entry->symbolicArguments];
        }
        $scope = ['mode' => $query->scope()->mode->value, 'entries' => $entries];
        if ($query->scope()->path !== null) {
            $scope['path'] = ['callers' => array_map((new \Deriver\Deriver\Lowerer\Declaration\CallableIdentity())->key(...), $query->scope()->path->callers), 'atCall' => $query->scope()->path->atCall];
        }
        return [...$record, 'scope' => $scope, 'budget' => $query->budget()];
    }

    /**
     * Identifies query content independently of shared object instances.
     * Each immutable reference is encoded separately so object sharing cannot affect the digest.
     * @param Query $query Requested observation
     * @return string Semantic content digest
     */
    public function key(Query $query): string
    {
        $graph = new ValueGraph();
        $record = $this->record($query, $graph);
        if ($query instanceof ValueQuery) {
            $record['expression'] = serialize($query->expression);
            $record['projection'] = serialize($query->projection);
        }
        if ($query instanceof StateQuery) {
            $record['point'] = serialize($query->point);
            $record['projection'] = serialize($query->projection);
        }
        if ($query instanceof TupleQuery) {
            $record['point'] = serialize($query->point);
            $record['values'] = array_map(static fn (ExpressionRef $expression): string => serialize($expression), $query->values);
        }
        $record['budget'] = serialize($query->budget());
        return hash('sha256', serialize([$record, $graph->records]));
    }
}
