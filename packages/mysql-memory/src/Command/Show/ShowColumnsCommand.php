<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Command\Command;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW [FULL] COLUMNS and DESCRIBE: one row for each column of a table, in the order of its columns.
 *
 * FULL adds the collation, the privileges and the comment of each column. LIKE, and the column
 * name or pattern DESCRIBE takes, match the column names without regard to case. The rows are
 * read from INFORMATION_SCHEMA.COLUMNS, which the column metadata names. A system table lists its
 * columns as the catalog of the release records them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-columns.html,
 * https://dev.mysql.com/doc/refman/8.4/en/explain.html.
 *
 * @visibility MySqlMemory
 */
final class ShowColumnsCommand implements Command
{
    /**
     * The privileges of the connected account on every column: the server runs as root.
     */
    public const PRIVILEGES = 'select,insert,update,references';

    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Lists the columns.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowColumns || $statement instanceof DescribeTable);
        (new Inspection())->warn($statement, $session);
        $full = $statement instanceof ShowColumns && $statement->listing?->full() === true;
        $system = (new Inspection())->system($statement->table, $statement instanceof ShowColumns ? $statement->database : null, $session);
        if ($system !== null) {
            return $this->system($system, $full, $operation, $session, $context, $connection);
        }
        $stored = (new Inspection())->table($statement->table, $statement instanceof ShowColumns ? $statement->database : null, $session);
        $definition = $stored->definition;
        $text = new ColumnText($session->settings()->release());
        $keys = new Keys();
        $rows = [];
        foreach ($definition->columns as $position => $column) {
            $collation = $text->textual($column->domain) ? $column->domain->collation->name : null;
            $row = [$column->name, $text->type($column->domain, $text->written($definition, $column))];
            if ($full) {
                $row[] = $collation;
            }
            array_push($row, $column->nullable() ? 'YES' : 'NO', $keys->role($definition, $position), ...($definition->temporary && !$text->legacy() ? $this->temporaryValues($column, $text) : [$text->shownDefault($column), $text->extra($column)]));
            if ($full) {
                array_push($row, self::PRIVILEGES, $column->comment);
            }
            $rows[] = $row;
        }
        $filter = $statement instanceof ShowColumns ? $statement->filter : ($statement->column === null ? null : new ShowLike(new Text($statement->column instanceof Text ? (new Inspection())->pattern($statement->column) : $statement->column->value)));

        $headings = $definition->temporary && !$text->legacy() ? $this->temporary($full) : $this->headings($full, $text->legacy());

        return (new Listing($headings))->result($rows, $operation, $session, $context, $connection, $filter, 0, 'utf8mb3_tolower_ci', 'EXPLICIT');
    }

    /**
     * Answers the default and the extra attributes of a column of a temporary table as the server lists them: an expression default as SHOW CREATE TABLE writes it, no DEFAULT_GENERATED, and NULL for no extra attribute (verified on a live 8.4 server).
     *
     * @return array{string|null, string}
     */
    public function temporaryValues(\MySqlMemory\Dictionary\ColumnDefinition $column, ColumnText $text): array
    {
        $default = $column->default->expression !== null && !$column->default->now && $column->default->declared ? '(' . $column->default->text . ')' : $text->shownDefault($column);
        $extra = trim(str_replace('DEFAULT_GENERATED', '', $text->extra($column)));

        return [$default, $extra === '' ? 'NULL' : (string) preg_replace('/ {2,}/', ' ', $extra)];
    }

    /**
     * Answers the columns of the rows that list the columns of a temporary table, which the server reads apart from INFORMATION_SCHEMA (verified on a live 8.4 server).
     *
     * @return list<Heading>
     */
    public function temporary(bool $full): array
    {
        return array_map(static fn (Heading $heading): Heading => new Heading($heading->name, $heading->field, $heading->name === 'Extra' ? 40 : $heading->length, $heading->flags, $heading->decimals, $heading->text, $heading->originalName, 'TMP_TABLE_COLUMNS', $heading->originalTable, $heading->schema, $heading->collation), $this->legacy($full));
    }

    /**
     * Lists the columns of a system table, as INFORMATION_SCHEMA.COLUMNS lists them.
     *
     * @throws \MySqlMemory\Error\SqlError When evaluating the condition fails
     */
    public function system(\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable $table, bool $full, Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowColumns || $statement instanceof DescribeTable);
        $legacy = $session->settings()->legacy();
        $rows = [];
        foreach ($table->columns as $column) {
            $row = [$column->name, $column->columnType];
            if ($full) {
                $row[] = $column->collation === null ? null : (\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named($column->collation)?->nameIn($session->settings()->release()) ?? $column->collation);
            }
            array_push($row, $column->listedNullable ? 'YES' : 'NO', $column->key, $column->default, $column->extra);
            if ($full) {
                array_push($row, $table->schema === 'information_schema' ? 'select' : self::PRIVILEGES, $column->comment);
            }
            $rows[] = $row;
        }
        $filter = $statement instanceof ShowColumns ? $statement->filter : ($statement->column === null ? null : new ShowLike(new Text($statement->column instanceof Text ? (new Inspection())->pattern($statement->column) : $statement->column->value)));

        return (new Listing($this->headings($full, $legacy)))->result($rows, $operation, $session, $context, $connection, $filter, 0, 'utf8mb3_tolower_ci', 'EXPLICIT');
    }

    /**
     * Answers the columns of the rows, with the collation, privileges and comment when FULL is written.
     *
     * @return list<Heading>
     */
    public function headings(bool $full, bool $legacy = false): array
    {
        if ($legacy) {
            return $this->legacy($full);
        }
        $headings = [Heading::text('Field', Field::VarString, 64, 0, 0, 'Field', 'COLUMNS'), Heading::text('Type', Field::Blob, 16777215, ColumnFlag::NotNull->value | ColumnFlag::Blob->value | ColumnFlag::Binary->value | ColumnFlag::NoDefaultValue->value, 0, 'Type', 'COLUMNS', 'columns')];
        if ($full) {
            $headings[] = Heading::text('Collation', Field::VarString, 64, 0, 0, 'Collation', 'COLUMNS');
        }
        array_push(
            $headings,
            Heading::text('Null', Field::VarString, 3, ColumnFlag::NotNull->value, 0, 'Null', 'COLUMNS'),
            Heading::text('Key', Field::String, 3, ColumnFlag::NotNull->value | ColumnFlag::Binary->value | ColumnFlag::Enum->value | ColumnFlag::NoDefaultValue->value, 0, 'Key', 'COLUMNS', 'columns'),
            Heading::text('Default', Field::Blob, 65535, ColumnFlag::Blob->value | ColumnFlag::Binary->value, 0, 'Default', 'COLUMNS', 'columns'),
            Heading::text('Extra', Field::VarString, 256, 0, 0, 'Extra', 'COLUMNS'),
        );
        if ($full) {
            array_push($headings, Heading::text('Privileges', Field::VarString, 154, 0, 0, 'Privileges', 'COLUMNS'), Heading::text('Comment', Field::Blob, 6144, ColumnFlag::NotNull->value | ColumnFlag::Blob->value | ColumnFlag::Binary->value, 0, 'Comment', 'COLUMNS'));
        }

        return $headings;
    }

    /**
     * Answers the columns of the rows as MySQL 5.6 and 5.7 send them, read from INFORMATION_SCHEMA.COLUMNS (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @return list<Heading>
     */
    public function legacy(bool $full): array
    {
        $required = ColumnFlag::NotNull->value;
        $headings = [Heading::text('Field', Field::VarString, 64, $required, 0, 'Field', 'COLUMNS'), Heading::text('Type', Field::Blob, 196605, $required | ColumnFlag::Blob->value, 0, 'Type', 'COLUMNS')];
        if ($full) {
            $headings[] = Heading::text('Collation', Field::VarString, 32, 0, 0, 'Collation', 'COLUMNS');
        }
        array_push(
            $headings,
            Heading::text('Null', Field::VarString, 3, $required, 0, 'Null', 'COLUMNS'),
            Heading::text('Key', Field::VarString, 3, $required, 0, 'Key', 'COLUMNS'),
            Heading::text('Default', Field::Blob, 196605, ColumnFlag::Blob->value, 0, 'Default', 'COLUMNS'),
            Heading::text('Extra', Field::VarString, 30, $required, 0, 'Extra', 'COLUMNS'),
        );
        if ($full) {
            array_push($headings, Heading::text('Privileges', Field::VarString, 80, $required, 0, 'Privileges', 'COLUMNS'), Heading::text('Comment', Field::VarString, 1024, $required, 0, 'Comment', 'COLUMNS'));
        }

        return $headings;
    }
}
