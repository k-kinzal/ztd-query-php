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
 * read from INFORMATION_SCHEMA.COLUMNS, which the column metadata names.
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
        $stored = (new Inspection())->table($statement->table, $statement instanceof ShowColumns ? $statement->database : null, $session);
        $full = $statement instanceof ShowColumns && $statement->listing?->full() === true;
        $definition = $stored->definition;
        $text = new ColumnText();
        $keys = new Keys();
        $rows = [];
        foreach ($definition->columns as $position => $column) {
            $collation = $text->textual($column->domain) ? $column->domain->collation->name : null;
            $row = [$column->name, $text->type($column->domain, $text->written($definition, $column))];
            if ($full) {
                $row[] = $collation;
            }
            array_push($row, $column->nullable() ? 'YES' : 'NO', $keys->role($definition, $position), $text->shownDefault($column), $text->extra($column));
            if ($full) {
                array_push($row, self::PRIVILEGES, $column->comment);
            }
            $rows[] = $row;
        }
        $filter = $statement instanceof ShowColumns ? $statement->filter : ($statement->column === null ? null : new ShowLike(new Text($statement->column instanceof Text ? (new Inspection())->pattern($statement->column) : $statement->column->value)));

        return (new Listing($this->headings($full)))->result($rows, $operation, $session, $context, $connection, $filter, 0, 'utf8mb3_tolower_ci', 'EXPLICIT');
    }

    /**
     * Answers the columns of the rows, with the collation, privileges and comment when FULL is written.
     *
     * @return list<Heading>
     */
    public function headings(bool $full): array
    {
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
}
