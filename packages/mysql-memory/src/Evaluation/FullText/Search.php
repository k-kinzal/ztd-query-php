<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\FullText;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextMode;

/**
 * Evaluates a bound full-text search against the relation's populated documents.
 *
 * The column expressions, search expression and mode come from Semantics. Search
 * words are runtime values; the evaluator never recovers or parses input SQL.
 *
 * @visibility MySqlMemory
 */
final class Search implements Evaluable
{
    /**
     * @param list<Evaluable> $columns Resolved columns of the current query frame
     * @param list<int> $positions Corresponding positions in stored documents
     */
    public function __construct(public readonly StoredTable $table, public readonly array $columns, public readonly array $positions, public readonly Evaluable $against, public readonly FullTextMode $mode, public readonly Domain $domain, public readonly Connection $connection)
    {
    }

    /**
     * Returns the semantic result domain, including its native metadata.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Computes relevance using the complete visible document collection.
     */
    #[Override]
    public function evaluate(Frame $frame): float
    {
        $text = Convert::toText($this->against->evaluate($frame), $this->against->domain()) ?? '';
        $myIsam = strcasecmp($this->table->definition->engine, 'MyISAM') === 0;
        $words = Words::forEngine($this->columns[0]->domain()->collation, $myIsam);
        $natural = Natural::read($text, $this->against->domain()->collation->charset, $words, $myIsam);
        $collection = $this->collection($words);
        $current = [];
        foreach ($this->columns as $column) {
            array_push($current, ...$words->split((string) $column->evaluate($frame), $column->domain()->collation->charset));
        }
        if ($this->mode === FullTextMode::Boolean) {
            $terms = BooleanInput::read($text, $this->against->domain()->collation->charset, $words, $myIsam);
            return (new BooleanSearch($terms, $words, $myIsam))->score(new Document($current, $words), $collection);
        }
        return $natural->score(new Document($current, $words), $collection, $this->mode === FullTextMode::QueryExpansion);
    }

    /**
     * Reads indexed column values from each visible stored document.
     */
    public function collection(Words $words): Collection
    {
        $documents = [];
        $rows = $this->connection->session()?->transaction->access->rows($this->table) ?? $this->table->data->rows;
        foreach ($rows as $row) {
            $tokens = [];
            foreach ($this->positions as $position) {
                array_push($tokens, ...$words->split((string) ($row[$position] ?? ''), $this->table->definition->columns[$position]->domain->collation->charset));
            }
            $documents[] = new Document($tokens, $words);
        }
        return new Collection($documents);
    }
}
