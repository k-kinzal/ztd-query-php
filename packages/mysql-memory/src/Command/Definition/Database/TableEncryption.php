<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Database;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption;

/**
 * Resolves new tables' encryption against their database default.
 *
 * Persistent tables inherit an omitted or empty setting. Temporary tables do
 * not inherit it and refuse nonempty explicit settings. An explicit N under a
 * Y database warns. Observed through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-data-encryption.html.
 *
 * @visibility MySqlMemory
 */
final class TableEncryption
{
    /**
     * Answers the last explicit encryption operand, or null when absent.
     */
    public function operand(CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create): ?string
    {
        $value = null;
        foreach ($create->options as $option) {
            if ($option instanceof TextOption && $option->kind === TextOptionKind::Encryption) {
                $value = $option->value->bytes();
            }
        }

        return $value;
    }

    /**
     * Answers the database default, without applying it to temporary tables.
     */
    public function inherited(CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create, Session $session): Encryption
    {
        $name = $create->name->schema->value ?? $session->variables->database;

        return $create->temporaryWords > 0 ? Encryption::Disabled : ($session->instance->dictionary->schema($name)->encryption ?? Encryption::Disabled);
    }

    /**
     * Answers the effective operand for the storage engine.
     */
    public function value(CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create, Session $session): string
    {
        $operand = $this->operand($create);

        return $operand === null || ($operand === '' && $create->temporaryWords === 0) ? $this->inherited($create, $session)->value : $operand;
    }

    /**
     * Checks table kind and engine capability before storage-engine validation.
     *
     * @throws SqlError When encryption is unavailable for this table kind or engine
     */
    public function check(CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create, string $engine, Session $session, Context $context): void
    {
        if ($session->settings()->legacy()) {
            return;
        }
        $operand = $this->operand($create);
        if ($create->temporaryWords > 0 && $operand !== null && $operand !== '') {
            throw SchemaError::TemporaryTableEncryption->error();
        }
        $value = strtoupper($this->value($create, $session));
        if ($engine !== 'InnoDB' && !in_array($value, ['', 'N'], true)) {
            throw SchemaError::EngineUnsupportedOperation->error('ENCRYPTION');
        }
        if ($value === 'N' && $this->inherited($create, $session) === Encryption::Enabled) {
            $context->warning(SchemaError::UnencryptedTable);
        }
    }
}
