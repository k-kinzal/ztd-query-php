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
 * Each generated CREATE TABLE statement runs on a disposable schema of a real
 * server. A statement the server rejects says nothing about the model and is
 * skipped. For one the server accepts, the statement must analyze into one
 * declaration, and the declaration must state what the catalog states: the
 * table name as stored, and for each column in order its stored name,
 * whether it can hold NULL, whether it is numbered automatically, and the
 * precision and scale an exact decimal type enforces.
 */
final class CatalogTarget
{
    private readonly string $schema;

    /**
     * Uses a schema of its own on the server, dropped at shutdown.
     */
    public function __construct(private readonly Semantics $semantics, private readonly PDO $pdo, private readonly string $grammarVersion)
    {
        $this->schema = 'semantics_catalog_' . bin2hex(random_bytes(6));
        register_shutdown_function(function (): void {
            $this->pdo->exec('DROP SCHEMA IF EXISTS ' . $this->schema . ' CASCADE');
        });
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
     * The facts a declaration states, in the shape the catalog is read in.
     *
     * @return array{string, list<array{string, bool, bool, int|null, int|null}>}
     */
    private static function facts(TableDefinition $table): array
    {
        $columns = [];
        foreach ($table->columns as $column) {
            $columns[] = [$column->name, $column->nullability === Nullability::MaybeNull, $column->autoIncrement, $column->type->effectiveNumericSize?->precision, $column->type->effectiveNumericSize?->scale];
        }

        return [$table->name, $columns];
    }

    /**
     * Starts every input on an empty schema, the only one on the search path.
     */
    private function reset(): void
    {
        $this->pdo->exec('DROP SCHEMA IF EXISTS ' . $this->schema . ' CASCADE');
        $this->pdo->exec('CREATE SCHEMA ' . $this->schema);
        $this->pdo->exec('SET search_path TO ' . $this->schema);
    }

    /**
     * The facts the catalog states about the one table of the schema.
     *
     * A numeric scale is sign-extended from its eleven bits, as the server's
     * numeric_typmod_scale() reads it, so a negative scale is read as written.
     *
     * @return array{string, list<array{string, bool, bool, int|null, int|null}>}
     */
    private function catalog(): array
    {
        $tables = $this->pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema()')->fetchAll(PDO::FETCH_COLUMN);
        $columns = [];
        foreach ($this->pdo->query('SELECT * FROM information_schema.columns WHERE table_schema = current_schema() ORDER BY ordinal_position')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $numeric = $row['data_type'] === 'numeric' && $row['numeric_precision'] !== null;
            $columns[] = [$row['column_name'], $row['is_nullable'] === 'YES', $row['is_identity'] === 'YES' || str_starts_with((string) $row['column_default'], 'nextval('), $numeric ? (int) $row['numeric_precision'] : null, $numeric ? (((int) $row['numeric_scale'] ^ 1024) - 1024) : null];
        }

        return [(string) ($tables[0] ?? ''), $columns];
    }
}
