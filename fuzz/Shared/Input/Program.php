<?php

declare(strict_types=1);

namespace Fuzz\Shared\Input;

use LogicException;

/**
 * One portable SQL program is run through exactly the same oracle on every database.
 *
 * Header bytes choose table count, numeric column count, fixture row count and
 * value offset. Each following sixteen-byte block chooses operation, table,
 * value, label and recursive expression/predicate alternatives. Missing bytes
 * decode as zero; at most 32 blocks are executed. Expected rejection is part
 * of the generated command, never inferred from the implementation's result.
 */
final class Program
{
    /**
     * Generated catalog shared by all database campaigns.
     */
    public readonly Schema $schema;
    /**
     * @var list<Command>
     */
    public readonly array $commands;

    /**
     * Decode a schema header and sixteen-byte SQL operation blocks.
     * @throws LogicException
     */
    public function __construct(string $input)
    {
        $this->schema = new Schema(substr($input, 0, 4));
        $commands = [];
        $nextId = 10;
        $transaction = false;
        $savepoint = false;
        foreach (str_split((strlen($input) > 4 ? substr($input, 4, 512) : "\0"), 16) as $chunk) {
            $bytes = new Bytes($chunk);
            $operation = $bytes->next(16);
            $index = $bytes->next(count($this->schema->tables));
            $table = $this->schema->tables[$index];
            $other = $this->schema->tables[($index + 1) % count($this->schema->tables)];
            $value = $bytes->next(65) - 32;
            $label = Schema::label($bytes->next());
            if ($operation >= 12 && $transaction) {
                $commands[] = new Command('COMMIT', 'transaction');
                $transaction = $savepoint = false;
            }
            if ($operation >= 6 && $operation <= 11) {
                $commands[] = self::transaction($operation, $transaction, $savepoint);
            } elseif ($operation === 15) {
                array_push($commands, new Command($this->schema->create('scratch'), 'ddl'), new Command('INSERT INTO scratch VALUES ' . $this->schema->values($nextId++, $value, $label), 'write'), new Command('ALTER TABLE scratch ADD COLUMN extra INTEGER NOT NULL DEFAULT 7', 'ddl', feature: 'alter-add'), new Command('SELECT * FROM scratch ORDER BY id'), new Command('DROP TABLE scratch', 'ddl'));
            } else {
                $commands[] = $this->command($operation, $bytes, $table, $other, $nextId++, $value, $label);
            }
        }
        if ($transaction) {
            $commands[] = new Command('ROLLBACK', 'transaction');
        }
        $this->commands = $commands;
    }

    /**
     * Build a supported SQL operation or an explicitly invalid statement.
     * @throws LogicException
     */
    public function command(int $operation, Bytes $bytes, string $table, string $other, int $id, int $value, ?string $label): Command
    {
        $expressions = new Expressions($bytes, $this->schema);
        return match ($operation) {
            0, 5 => new Command(SelectQuery::sql($bytes, $this->schema, $table, $other)),
            1 => new Command("INSERT INTO $table VALUES " . $this->schema->values($id, $value, $label), 'write'),
            2 => new Command("UPDATE $table SET v0 = (" . $expressions->number() . ') % 10000 WHERE ' . $expressions->predicate($other), 'write'),
            3 => new Command("DELETE FROM $table WHERE " . $expressions->predicate($other), 'write'),
            4 => new Command("INSERT INTO $table VALUES " . $this->schema->values($id, $value, $label) . ', ' . $this->schema->values($id + 1000, -$value, $label), 'write'),
            12 => new Command("INSERT INTO $table VALUES " . $this->schema->values($id, $value, $label) . ', ' . $this->schema->values($id, -$value, $label), 'write', 'unique'),
            13 => new Command("INSERT INTO $table (id, v0) VALUES ($id, NULL)", 'write', 'not-null'),
            14 => new Command('SELECT * FROM missing_table', 'read', 'table'),
            default => throw new LogicException('Unrecognized SQL operation.'),
        };
    }

    /**
     * Generate valid transaction and savepoint sequences.
     */
    public static function transaction(int $operation, bool &$open, bool &$saved): Command
    {
        if (!$open) {
            $open = true;
            return new Command('BEGIN', 'transaction');
        }
        if ($operation === 7 || $operation === 8) {
            $open = $saved = false;
            return new Command($operation === 7 ? 'COMMIT' : 'ROLLBACK', 'transaction');
        }
        if (!$saved || $operation === 9 || $operation === 6) {
            $saved = true;
            return new Command('SAVEPOINT checkpoint', 'transaction');
        }
        $saved = $operation !== 11;
        return new Command($operation === 11 ? 'RELEASE SAVEPOINT checkpoint' : 'ROLLBACK TO SAVEPOINT checkpoint', 'transaction');
    }
}
