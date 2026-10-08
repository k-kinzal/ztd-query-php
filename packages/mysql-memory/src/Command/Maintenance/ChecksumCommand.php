<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Maintenance;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumMode;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Operation;

/**
 * Executes CHECKSUM TABLE: a row of Table and Checksum for each table in written order.
 *
 * A table that does not exist, whose database does not exist, or that is a view, has a NULL
 * checksum and adds its error to the warnings. QUICK answers NULL for an InnoDB table, which
 * keeps no live checksum. An empty table checksums to 0. The checksum of rows depends on the storage format of
 * the server; the emulator answers a CRC-32 based value of the stored rows, which is stable but
 * not the server's (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/checksum-table.html.
 *
 * @visibility MySqlMemory
 */
final class ChecksumCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Answers the checksum of each table.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ChecksumTable);
        $database = $session->variables->database;
        foreach ($statement->tables as $table) {
            if ($table->name->schema === null && $database === '') {
                throw QueryError::NoDatabase->error();
            }
        }
        $rows = [];
        foreach ($statement->tables as $table) {
            $schema = $table->name->schema->value ?? $database;
            $stored = $session->instance->dictionary->table($schema, $table->name->name->value);
            $label = $schema . '.' . $table->name->name->value;
            if ($stored === null) {
                $owner = $session->instance->dictionary->schema($schema);
                $error = match (true) {
                    $owner === null => \MySqlMemory\Session\Problem\Errors::unknown($schema, $table->name->name->value, $session->settings()->release()),
                    isset($owner->views[$table->name->name->value]) => SchemaError::WrongObject->error($schema, $table->name->name->value, 'BASE TABLE'),
                    default => QueryError::NoSuchTable->error($schema, $table->name->name->value),
                };
                $session->diagnostics->error($error->getCode(), $error->getMessage());
                $rows[] = [$label, null];

                continue;
            }
            $rows[] = [$label, $statement->mode === ChecksumMode::Quick ? null : $this->checksum($stored->data->rows)];
        }
        $results = $session->variables->read('character_set_results');
        $charset = (is_string($results) ? Charset::named($results) : null) ?? Charset::known('utf8mb3');
        $columns = [
            new ResultColumn('Table', Field::VarString, 384 * $charset->maxLength, 31, 0, $charset->defaultCollation(GrammarRelease::MySql847)->id),
            new ResultColumn('Checksum', Field::LongLong, $session->settings()->legacy() ? 21 : 22, 0, ColumnFlag::Binary->value | ColumnFlag::Numeric->value, 63),
        ];

        return new ResultSet($columns, $rows, $context->diagnostics->count());
    }

    /**
     * Answers the checksum of rows: the sum of the CRC-32 of each row, 0 for no rows.
     *
     * @param array<int, list<int|float|string|null>> $rows
     */
    public function checksum(array $rows): string
    {
        $sum = 0;
        foreach ($rows as $row) {
            $sum = ($sum + crc32(serialize($row))) % 4294967296;
        }

        return (string) $sum;
    }
}
