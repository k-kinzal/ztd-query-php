<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation;

use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Components\OptionsArray;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use ZtdQuery\Platform\MySql\MySqlStatementOptions;

/**
 * Reads the column an ALTER TABLE operation is about.
 *
 * The parser hands an operation back as a name, a bag of options, and a run of
 * tokens it could not place. What a column is called and how it is declared
 * both have to be read back out of that, and the only reader that can be
 * trusted with a declaration is the one that reads CREATE TABLE — so a
 * declaration is put back into one and parsed again.
 */
final class AlterTableColumn
{
    /**
     * @param MySqlStatementOptions $options Reports which of a statement's optional words were written
     */
    public function __construct(private readonly MySqlStatementOptions $options = new MySqlStatementOptions())
    {
    }

    /**
     * Answers the name the operation gives its column.
     *
     * @param AlterOperation $op Operation to read
     *
     * @return string|null The name, or null where the operation names no column
     */
    public function nameIn(AlterOperation $op): ?string
    {
        $field = $op->field;
        if ($field === null) {
            return null;
        }

        $name = is_string($field) ? $field : ($field->column ?? $field->expr ?? null);
        if (!is_string($name)) {
            return null;
        }

        return $this->withoutQuotes($name);
    }

    /**
     * Answers a name as the table knows it, rather than as it was written.
     *
     * @param string $name Name as the statement wrote it
     *
     * @return string The same name, with the quoting taken off
     */
    public function withoutQuotes(string $name): string
    {
        return str_replace('`', '', $name);
    }

    /**
     * Answers the column declaration an ADD or MODIFY writes.
     *
     * @param AlterOperation $op Operation to read
     *
     * @return CreateDefinition|null The declaration, or null where the operation writes none
     */
    public function definitionIn(AlterOperation $op): ?CreateDefinition
    {
        $columnName = $this->nameIn($op);
        if ($columnName === null) {
            return null;
        }

        return $this->firstFieldOf('CREATE TABLE t (`' . $columnName . '` ' . $this->unplacedText($op) . ')');
    }

    /**
     * Answers the column declaration a CHANGE writes, name and all.
     *
     * A CHANGE names the column it replaces separately, so everything the
     * parser could not place is the new declaration, the new name included.
     *
     * @param AlterOperation $op Operation to read
     *
     * @return CreateDefinition|null The declaration, or null where the operation writes none
     */
    public function redefinitionIn(AlterOperation $op): ?CreateDefinition
    {
        $text = $this->unplacedText($op);
        if ($text === '') {
            return null;
        }

        return $this->firstFieldOf('CREATE TABLE t (' . $text . ')');
    }

    /**
     * Answers everything in the operation the parser could not place, as written.
     *
     * @param AlterOperation $op Operation to read
     *
     * @return string The unplaced text, run together as the statement wrote it
     */
    public function unplacedText(AlterOperation $op): string
    {
        $text = '';
        foreach (is_array($op->unknown) ? $op->unknown : [] as $token) {
            $text .= $token->token;
        }

        return $text;
    }

    /**
     * Reports whether the operation asks for something ZTD cannot simulate.
     *
     * A spatial index or a partition change reaches past what the shadow
     * models, and the parser leaves both among the tokens it could not place.
     *
     * @param AlterOperation $op Operation to read
     *
     * @return bool True when the operation names one of them
     */
    public function mentionsUnsupported(AlterOperation $op): bool
    {
        foreach (is_array($op->unknown) ? $op->unknown : [] as $token) {
            $value = strtoupper(is_string($token->value) ? $token->value : '');
            foreach (['SPATIAL INDEX', 'SPATIAL KEY', 'PARTITION'] as $pattern) {
                if (str_contains($value, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Reports whether an option was written.
     *
     * @param OptionsArray $options Options the parser read off the operation
     * @param string $name Option to look for
     *
     * @return bool True when the statement wrote it
     */
    public function optionIsSet(OptionsArray $options, string $name): bool
    {
        return $this->options->isSet($options, $name);
    }

    /**
     * Answers the one column a reconstructed CREATE TABLE declares.
     *
     * @param string $sql CREATE TABLE written around the declaration
     *
     * @return CreateDefinition|null The declaration, or null where it could not be read
     */
    public function firstFieldOf(string $sql): ?CreateDefinition
    {
        $parser = new Parser($sql);
        $statement = $parser->statements[0] ?? null;
        if (!$statement instanceof CreateStatement || !is_array($statement->fields) || $statement->fields === []) {
            return null;
        }

        return $statement->fields[0];
    }
}
