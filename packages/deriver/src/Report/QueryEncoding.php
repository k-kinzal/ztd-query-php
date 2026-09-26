<?php

declare(strict_types=1);

namespace Deriver\Report;

use Deriver\Api\InvalidInputException;
use Deriver\Api\Query\Query;
use Deriver\Api\Query\ReturnQuery;
use Deriver\Api\Query\StateQuery;
use Deriver\Api\Query\TupleQuery;
use Deriver\Api\Query\ValueQuery;
use JsonException;

/**
 * Encodes every semantic query option, including scope and structural budgets.
 * @phpstan-import-type ScalarRecord from ValueGraph
 * @phpstan-type EntryRecord array{symbol: string, arguments: list<array{name: ScalarRecord, value: string}>, receiver: string|null}
 * @phpstan-type QueryRecord array{kind: string, symbol?: string, expression?: \Deriver\Api\Reference\ExpressionRef, point?: \Deriver\Api\Reference\PointRef, variable?: string, projection?: \Deriver\Value\Projection, values?: object, scope: array{mode: string, entries: list<EntryRecord>}, budget: \Deriver\Api\Query\Budget}
 * @visibility root
 */
final class QueryEncoding
{
    /**
     * Normalizes a query with entry values in the shared lossless graph.
     * @param Query $query Requested observation
     * @param ValueGraph $graph Value encoding and redaction policy
     * @return QueryRecord Versioned query fields
     * @throws InvalidInputException If the query implementation is unsupported
     */
    public function record(Query $query, ValueGraph $graph): array
    {
        $record = match (true) {
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
            $entries[] = ['symbol' => $entry->symbol, 'arguments' => $arguments, 'receiver' => $entry->receiver === null ? null : $graph->add($entry->receiver)];
        }
        return [...$record, 'scope' => ['mode' => $query->scope()->mode, 'entries' => $entries], 'budget' => $query->budget()];
    }

    /**
     * Identifies an immutable query independently of object serialization.
     * @param Query $query Requested observation
     * @return string Semantic content digest
     * @throws JsonException If query metadata cannot be represented in JSON
     */
    public function key(Query $query): string
    {
        $graph = new ValueGraph(true);
        $record = $this->record($query, $graph);
        $encoded = json_encode((new JsonText())->tree([$record, $graph->records]), JSON_THROW_ON_ERROR);
        $confidential = false;
        foreach ($query->scope()->entries as $entry) {
            $confidential = $confidential || $entry->receiver?->isSecret() === true;
            foreach ($entry->arguments as $argument) {
                $confidential = $confidential || $argument->isSecret();
            }
        }
        return $confidential ? (new \Deriver\Internal\Value\SecretFingerprint())->digest($encoded) : hash('sha256', $encoded);
    }
}
