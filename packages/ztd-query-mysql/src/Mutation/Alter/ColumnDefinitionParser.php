<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation\Alter;

use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;

/**
 * Column Definition Parser.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ColumnDefinitionParser
{
    /**
     * Build Column Definition for the supplied MySQL input.
     */
    public function buildColumnDefinition(AlterOperation $op): ?CreateDefinition
    {
        $field = $op->field;
        if ($field === null) {
            return null;
        }

        $columnName = is_string($field) ? $field : ($field->column ?? $field->expr ?? null);
        if (!is_string($columnName)) {
            return null;
        }

        $columnName = (str_replace('`', '', $columnName));

        $tokens = is_array($op->unknown) ? $op->unknown : [];
        $typeStr = '';
        foreach ($tokens as $token) {
            $typeStr .= $token->token;
        }

        $defSql = "CREATE TABLE t (`$columnName` $typeStr)";
        $parser = new \PhpMyAdmin\SqlParser\Parser($defSql);
        if ($parser->statements === [] || !$parser->statements[0] instanceof CreateStatement) {
            return null;
        }

        $tempCreate = $parser->statements[0];
        if (!is_array($tempCreate->fields) || $tempCreate->fields === []) {
            return null;
        }

        return $tempCreate->fields[0];
    }

    /**
     * Build Column Definition From Unknown for the supplied MySQL input.
     */
    public function buildColumnDefinitionFromUnknown(AlterOperation $op): ?CreateDefinition
    {
        $tokens = is_array($op->unknown) ? $op->unknown : [];
        if ($tokens === []) {
            return null;
        }

        $tokenStr = '';
        foreach ($tokens as $token) {
            $tokenStr .= $token->token;
        }

        $defSql = "CREATE TABLE t ($tokenStr)";
        $parser = new \PhpMyAdmin\SqlParser\Parser($defSql);
        if ($parser->statements === [] || !$parser->statements[0] instanceof CreateStatement) {
            return null;
        }

        $tempCreate = $parser->statements[0];
        if (!is_array($tempCreate->fields) || $tempCreate->fields === []) {
            return null;
        }

        return $tempCreate->fields[0];
    }

    /**
     * Get Column Name for the supplied MySQL input.
     */
    public function getColumnName(AlterOperation $op): ?string
    {
        $field = $op->field;
        if ($field === null) {
            return null;
        }

        $name = is_string($field) ? $field : ($field->column ?? $field->expr ?? null);
        if (!is_string($name)) {
            return null;
        }

        return (str_replace('`', '', $name));
    }
}
