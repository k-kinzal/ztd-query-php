<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation\Alter;

use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Token;

/**
 * Primary Key Alteration.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class PrimaryKeyAlteration
{
    /**
     * Apply Add Primary Key for the supplied MySQL input.
     */
    public function applyAddPrimaryKey(CreateStatement $createStmt, AlterOperation $op): void
    {
        $keyDef = new CreateDefinition();
        $keyDef->key = new \PhpMyAdmin\SqlParser\Components\Key();
        $keyDef->key->type = 'PRIMARY KEY';
        $keyDef->key->columns = [];

        $unknownTokens = is_array($op->unknown) ? $op->unknown : [];
        foreach ($unknownTokens as $token) {
            if ($token->type === Token::TYPE_SYMBOL) {
                $tokenValue = is_string($token->value) ? $token->value : '';
                $colName = str_replace('`', '', $tokenValue);
                $keyDef->key->columns[] = ['name' => $colName];
            }
        }

        if (!is_array($createStmt->fields)) {
            $createStmt->fields = [];
        }
        $createStmt->fields[] = $keyDef;
    }

    /**
     * Apply Drop Primary Key for the supplied MySQL input.
     */
    public function applyDropPrimaryKey(CreateStatement $createStmt): void
    {
        if (!is_array($createStmt->fields)) {
            return;
        }

        foreach ($createStmt->fields as $field) {
            if ($field->options !== null && ($field->options->has('PRIMARY KEY') !== false)) {
                $field->options->remove('PRIMARY KEY');
            }
        }

        $createStmt->fields = array_values(array_filter(
            $createStmt->fields,
            fn ($field) => $field->key === null || $field->key->type !== 'PRIMARY KEY'
        ));
    }
}
