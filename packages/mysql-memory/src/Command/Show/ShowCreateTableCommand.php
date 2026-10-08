<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Server\ServerCatalog;
use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateTable;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW CREATE TABLE: the statement that creates a table as it is now.
 *
 * Each column is written with its type, a character set and collation that differ from the
 * table's, NOT NULL (or NULL for a TIMESTAMP that takes NULL), its default, ON UPDATE,
 * AUTO_INCREMENT, INVISIBLE and its comment, a generated column its expression and storage in
 * place of a default; then the keys in the order the server keeps them; then the foreign keys and
 * the CHECK constraints (ConstraintText); then the options: the engine, the next AUTO_INCREMENT value when it is above 1, the character
 * set, its collation unless it is the default collation of a character set other than utf8mb4,
 * and the comment. The column of the statement is as long as the statement, at least 1024
 * characters (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-table.html.
 *
 * @visibility MySqlMemory
 */
final class ShowCreateTableCommand implements Command
{
    /**
     * @param GrammarRelease $release The release whose CREATE TABLE text is written
     */
    public function __construct(public readonly GrammarRelease $release = GrammarRelease::MySql847)
    {
    }

    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Writes the statement.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowCreateTable);
        $stored = (new Inspection())->table($statement->table, null, $session);
        $view = $session->instance->dictionary->schema($stored->definition->schema)->views[$stored->definition->name] ?? null;
        if ($view !== null && $view->declaration === $stored->definition->declaration) {
            return (new \MySqlMemory\Command\View\ShowCreateViewCommand())->write($view, $session, $context);
        }
        $text = (new self($session->settings()->release()))->statement($stored);
        $headings = [
            Heading::text('Table', Field::VarString, 64, ColumnFlag::NotNull->value, 31),
            Heading::text('Create Table', Field::VarString, max(1024, mb_strlen($text, 'UTF-8')), ColumnFlag::NotNull->value, 31),
        ];

        return (new Listing($headings))->sent([[$stored->definition->name, $text]], $context);
    }

    /**
     * Writes the CREATE TABLE statement of a stored table.
     */
    public function statement(StoredTable $stored): string
    {
        $table = $stored->definition;
        $lines = array_map(fn (ColumnDefinition $column): string => '  ' . $this->column($column, $table), $table->columns);
        foreach ((new Keys())->ordered($table) as $key) {
            $lines[] = '  ' . $this->key($key, $table);
        }
        foreach ((new ConstraintText($this->release === GrammarRelease::MySql5651 || $this->release === GrammarRelease::MySql5744))->lines($table) as $line) {
            $lines[] = '  ' . $line;
        }

        return 'CREATE ' . ($table->temporary ? 'TEMPORARY ' : '') . 'TABLE ' . $this->name($table->name) . " (\n" . implode(",\n", $lines) . "\n) " . $this->options($stored) . ($table->partitioning === null ? '' : "\n" . $table->partitioning->text);
    }

    /**
     * Writes the definition of a column.
     */
    public function column(ColumnDefinition $column, TableDefinition $table): string
    {
        $text = new ColumnText($this->release);
        $domain = $column->domain;
        $type = $text->written($table, $column);
        $written = $this->name($column->name) . ' ' . $text->type($domain, $type);
        $written .= $this->collation($column, $table);
        if ($column->generated !== null) {
            $written .= ' GENERATED ALWAYS AS (' . $column->expression . ')' . ($column->stored ? ' STORED' : ' VIRTUAL');
        }
        if (!$column->nullable()) {
            $written .= ' NOT NULL';
        } elseif ($domain->field === Field::Timestamp) {
            $written .= ' NULL';
        }
        $written .= $column->generated === null ? $text->createDefault($column, $type) : '';
        if ($column->onUpdateNow) {
            $written .= ' ON UPDATE CURRENT_TIMESTAMP' . ($domain->decimals > 0 ? '(' . $domain->decimals . ')' : '');
        }
        if ($column->autoIncrement) {
            $written .= ' AUTO_INCREMENT';
        }
        if ($column->invisible) {
            $written .= ' /*!80023 INVISIBLE */';
        }
        if ($column->comment !== '') {
            $written .= ' COMMENT ' . $text->quoted($column->comment);
        }

        return $written;
    }

    /**
     * Writes the character set and collation of a text column.
     *
     * A collation the column names, or other than the table's, is written with its character
     * set; the table's collation the column takes is written alone when it is not the default
     * collation of its character set (verified on a live 8.4 server). MySQL 5.6 and 5.7 write
     * nothing for the table's collation, and the character set alone for the default collation
     * of another set (verified on a live 5.7.44 server).
     */
    public function collation(ColumnDefinition $column, TableDefinition $table): string
    {
        $text = new ColumnText($this->release);
        $collation = $column->domain->collation;
        if (!$text->textual($column->domain)) {
            return '';
        }
        if ($text->legacy()) {
            return $collation->name === $table->collation ? '' : ' CHARACTER SET ' . $collation->charset->nameIn($this->release) . ($collation->charset->defaultCollation($this->release)->name === $collation->name ? '' : ' COLLATE ' . $collation->nameIn($this->release));
        }
        if ($collation->name !== $table->collation || $text->explicit($text->element($table, $column))) {
            return ' CHARACTER SET ' . $collation->charset->name . ' COLLATE ' . $collation->name;
        }

        return $collation->charset->defaultCollation(GrammarRelease::MySql847)->name === $collation->name ? '' : ' COLLATE ' . $collation->name;
    }

    /**
     * Writes the definition of a key.
     */
    public function key(Key $key, TableDefinition $table): string
    {
        $parts = [];
        foreach ($key->columns as $index => $position) {
            $prefix = $key->prefixes[$index] ?? null;
            $parts[] = $this->name($table->columns[$position]->name) . ($prefix === null ? '' : '(' . $prefix . ')') . (($key->descending[$index] ?? false) ? ' DESC' : '');
        }
        $columns = '(' . implode(',', $parts) . ')';

        return match ($key->kind) {
            KeyKind::Primary => 'PRIMARY KEY ' . $columns,
            KeyKind::Unique => 'UNIQUE KEY ' . $this->name($key->name) . ' ' . $columns,
            KeyKind::Index => 'KEY ' . $this->name($key->name) . ' ' . $columns,
            KeyKind::FullText => 'FULLTEXT KEY ' . $this->name($key->name) . ' ' . $columns,
            KeyKind::Spatial => 'SPATIAL KEY ' . $this->name($key->name) . ' ' . $columns,
        };
    }

    /**
     * Writes the options of a table.
     */
    public function options(StoredTable $stored): string
    {
        $table = $stored->definition;
        $collation = Collation::named($table->collation) ?? Collation::known('utf8mb4_0900_ai_ci');
        $charset = $collation->charset;
        $options = 'ENGINE=' . (ServerCatalog::shared()->engine($table->engine) ?? $table->engine);
        if ($stored->data->autoIncrement > 1) {
            $options .= ' AUTO_INCREMENT=' . sprintf('%u', $stored->data->autoIncrement);
        }
        $legacy = $this->release === GrammarRelease::MySql5651 || $this->release === GrammarRelease::MySql5744;
        $options .= ' DEFAULT CHARSET=' . $charset->nameIn($this->release);
        if ((!$legacy && $charset->name === 'utf8mb4') || $charset->defaultCollation($legacy ? $this->release : GrammarRelease::MySql847)->name !== $collation->name) {
            $options .= ' COLLATE=' . $collation->nameIn($this->release);
        }
        $comment = $this->comment($table);
        if ($comment !== '') {
            $options .= ' COMMENT=' . (new ColumnText())->quoted($comment);
        }

        return $options;
    }

    /**
     * Answers the comment of a table: the one it holds, else the COMMENT option of the statement that declared it.
     */
    public function comment(TableDefinition $table): string
    {
        $comment = $table->comment;
        foreach ($table->statement->options ?? [] as $option) {
            if ($comment === '' && $option instanceof TextOption && $option->kind === TextOptionKind::Comment) {
                $comment = $option->value->value;
            }
        }

        return $comment;
    }

    /**
     * Quotes an identifier as the server writes it: between backticks, a backtick doubled.
     */
    public function name(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
