<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use PDO;
use PDOException;
use SqlSemantics\Core\Declarations;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TableDefinition;
use Throwable;

/**
 * Every declaration the server accepts must declare the table the server's catalog then holds.
 *
 * Each generated CREATE TABLE statement runs on a new in-memory database of
 * the SQLite library. A statement SQLite rejects says nothing about the model
 * and is skipped. For one the server accepts, the statement must analyze into one
 * declaration, and the declaration must state what the catalog states: the
 * table name as stored, and for each column in order its stored name,
 * whether it can hold NULL, whether it is numbered automatically, and the
 * precision and scale an exact decimal type enforces.
 */
final class CatalogTarget
{
    private PDO $pdo;

    /**
     * Opens a new in-memory database for every input.
     */
    public function __construct(private readonly Semantics $semantics, private readonly string $grammarVersion)
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    /**
     * Records a declaration the server accepts but the model rejects or states differently as a finding.
     */
    public function verify(string $sql, string $input): void
    {
        $this->reset();
        try {
            $this->pdo->exec($sql);
        } catch (PDOException) {
            return;
        }
        $context = "Grammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}";
        try {
            $declarations = $this->semantics->analyze($sql, [], Declarations::Partial)->resolution?->declarations ?? [];
        } catch (Throwable $failure) {
            throw new Error("The server accepts a declaration the model rejects\n{$context}\nError: " . $failure::class . ': ' . $failure->getMessage(), 0, $failure);
        }
        if (count($declarations) !== 1) {
            throw new Error("The server accepts a declaration the model does not read as one table\n{$context}");
        }
        $model = self::facts($declarations[0]);
        $catalog = $this->catalog();
        if ($model !== $catalog) {
            throw new Error("The declaration states other facts than the catalog\n{$context}\nModel: " . json_encode($model, JSON_INVALID_UTF8_SUBSTITUTE) . "\nCatalog: " . json_encode($catalog, JSON_INVALID_UTF8_SUBSTITUTE));
        }
    }

    /**
     * The facts a declaration states that the SQLite catalog also states, in the shape it is read in.
     *
     * @return array{string, list<array{string, bool, bool, int|null, int|null}>}
     */
    private static function facts(TableDefinition $table): array
    {
        $columns = [];
        foreach ($table->columns as $column) {
            $columns[] = [$column->name, $column->nullability === Nullability::MaybeNull, false, null, null];
        }

        return [$table->name, $columns];
    }

    /**
     * Starts every input on an empty in-memory database.
     */
    private function reset(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    /**
     * The facts the catalog states about the one table of the database.
     *
     * SQLite numbers only an INTEGER PRIMARY KEY, as an alias of the row id,
     * and enforces no decimal size, so the catalog states names and NULL.
     *
     * @return array{string, list<array{string, bool, bool, int|null, int|null}>}
     */
    private function catalog(): array
    {
        $tables = $this->pdo->query("SELECT name FROM sqlite_schema WHERE type = 'table' AND name NOT LIKE 'sqlite\\_%' ESCAPE '\\'")->fetchAll(PDO::FETCH_COLUMN);
        $table = (string) ($tables[0] ?? '');
        $columns = [];
        foreach ($this->pdo->query('PRAGMA table_xinfo("' . str_replace('"', '""', $table) . '")')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[] = [$row['name'], (int) $row['notnull'] === 0, false, null, null];
        }

        return [$table, $columns];
    }
}
