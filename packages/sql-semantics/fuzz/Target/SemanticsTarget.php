<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use PDO;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Analyzer;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Catalog;
use SqlSemantics\Type\Nullability;

/**
 * Checks generated outer joins against SQLite as an independent execution oracle.
 */
final class SemanticsTarget
{
    private readonly SqliteParser $parser;

    private readonly Analyzer $analyzer;

    private readonly Catalog $catalog;

    private readonly PDO $database;

    /**
     * Declares a schema and independent in-memory database once per fuzz process.
     */
    public function __construct()
    {
        $ddl = 'CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)';
        $this->parser = new SqliteParser();
        $this->analyzer = new Analyzer(Dialect::Sqlite);
        $this->catalog = $this->analyzer->schema($this->parser->parse($ddl));
        $this->database = new PDO('sqlite::memory:');
        $this->database->exec($ddl);
    }

    /**
     * Verifies NULL guarantees, aliases, and occurrence lineage on generated rows.
     *
     * @throws Error When static guarantees disagree with actual SQL results
     */
    public function verify(string $input): void
    {
        $bytes = $input === '' ? [] : array_map(ord(...), str_split(substr($input, 0, 32)));
        $this->database->exec('DELETE FROM users');
        $insert = $this->database->prepare('INSERT INTO users VALUES (?, ?, ?)');
        foreach ($bytes as $index => $byte) {
            $insert->execute([$index + 1, $byte % 5 === 0 ? null : $byte % 35, $byte]);
        }
        $join = ($bytes[0] ?? 0) % 2 === 0 ? 'LEFT' : 'INNER';
        $alias = 'p' . ($bytes[1] ?? 0);
        $sql = 'SELECT c.id, ' . $alias . '.score AS parent_score, COALESCE(' . $alias . '.score, 0) AS effective_score FROM users c ' . $join . ' JOIN users ' . $alias . ' ON c.parent_id=' . $alias . '.id';
        $query = $this->analyzer->analyze($this->parser->parse($sql), $this->catalog);
        $statement = $this->database->query($sql);
        if ($statement === false) {
            throw new Error('SQLite did not execute the generated query.');
        }
        $rows = $statement->fetchAll(PDO::FETCH_NUM);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new Error('SQLite did not return a row array.');
            }
            foreach ($query->outputs as $output) {
                if ($output->expression->nullability === Nullability::NotNull && $row[$output->ordinal] === null) {
                    throw new Error('Incorrect NotNull guarantee for ' . $sql);
                }
            }
        }
        if ($query->outputs[1]->expression->lineage()[0]->relationId !== 'r1') {
            throw new Error('Self-join lineage collapsed two occurrences.');
        }
        if ($query->outputs[2]->expression->nullability !== Nullability::NotNull) {
            throw new Error('COALESCE with a non-null fallback lost its guarantee.');
        }
    }
}
