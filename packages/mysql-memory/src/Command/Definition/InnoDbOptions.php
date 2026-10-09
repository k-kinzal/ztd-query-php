<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Program\ProgramSource;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\AutoextendSizeOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\RowFormatOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption;

/**
 * Validates InnoDB table options without creating files or reading native implementation code.
 *
 * Unsupported row formats and compression algorithms warn before strict-mode rejection.
 * Encryption and file-extension limits report their specific failure followed by the engine
 * failure. Verified by executing DDL on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 *
 * @visibility MySqlMemory
 */
final class InnoDbOptions
{
    /**
     * Checks the final value of each storage option.
     *
     * @throws SqlError When an InnoDB option is refused
     */
    public function check(CreateTable $create, Session $session, Context $context): void
    {
        $row = null;
        $text = [];
        $size = null;
        foreach ($create->options as $option) {
            if ($option instanceof RowFormatOption) {
                $row = $option->format;
            } elseif ($option instanceof TextOption) {
                $text[$option->kind->value] = $option->value->bytes();
            } elseif ($option instanceof AutoextendSizeOption) {
                $size = $option->size;
            }
        }
        $name = $create->name->name->value;
        $strict = ProgramSource::enabled($session->variables->read('innodb_strict_mode'));
        $refused = $row === RowFormat::Fixed;
        if ($refused) {
            $default = strtoupper((string) ($session->variables->read('innodb_default_row_format') ?? 'COMPACT'));
            $context->diagnostics->warning(SchemaError::IllegalCreateOption, $strict ? 'InnoDB: invalid ROW_FORMAT specifier.' : "InnoDB: assuming ROW_FORMAT={$default}.");
        }
        $compression = $text[TextOptionKind::Compression->value] ?? '';
        if (!in_array(strtolower($compression), ['', 'none', 'zlib', 'lz4'], true)) {
            $context->diagnostics->warning(SchemaError::UnsupportedExtension, "InnoDB: Unsupported compression algorithm '{$compression}'");
            $refused = true;
        }
        $this->encryption($text[TextOptionKind::Encryption->value] ?? 'N', $name);
        if ($size !== null) {
            $bytes = (new \MySqlMemory\Command\Admin\Literals())->size($size);
            if ($bytes !== '0' && (bccomp($bytes, '4194304') < 0 || bccomp($bytes, '4294967296') > 0)) {
                throw new SqlError(SchemaError::AutoextendSize, SchemaError::AutoextendSize->message(), null, [[SchemaError::IllegalHa->value, SchemaError::IllegalHa->message($name)]]);
            }
        }
        if ($refused && $strict) {
            throw SchemaError::IllegalHa->error($name);
        }
    }

    /**
     * Checks the encryption flag against the absence of an installed keyring.
     *
     * @throws SqlError When the flag is invalid or requests encryption
     */
    public function encryption(string $value, string $table): void
    {
        if (!in_array(strtoupper($value), ['', 'N', 'Y'], true)) {
            throw new SqlError(SchemaError::InvalidEncryption, SchemaError::InvalidEncryption->message(), null, [[SchemaError::IllegalHa->value, SchemaError::IllegalHa->message($table)]]);
        }
        if (strtoupper($value) === 'Y') {
            throw new SqlError(AdministrationError::KeyringMissing, AdministrationError::KeyringMissing->message(), null, [[SchemaError::UnsupportedExtension->value, SchemaError::UnsupportedExtension->message($table)]]);
        }
    }
}
