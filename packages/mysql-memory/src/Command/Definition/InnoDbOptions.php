<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Program\ProgramSource;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Variables;
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
    public function check(CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create, Session $session, Context $context): void
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
        $refused = $this->refusedFormat($row, $session->variables, $context);
        $compression = $text[TextOptionKind::Compression->value] ?? '';
        if (!in_array(strtolower($compression), ['', 'none', 'zlib', 'lz4'], true)) {
            $context->diagnostics->warning(SchemaError::UnsupportedExtension, "InnoDB: Unsupported compression algorithm '{$compression}'");
            $refused = true;
        }
        $this->tableEncryption($create, $session);
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
     * Reads the last explicit ROW_FORMAT, retaining DEFAULT separately from omission.
     */
    public function requested(CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create): ?RowFormat
    {
        $format = null;
        foreach ($create->options as $option) {
            if ($option instanceof RowFormatOption) {
                $format = $option->format;
            }
        }

        return $format;
    }

    /**
     * Resolves an accepted definition against the current instance settings.
     */
    public function format(CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create, Variables $variables): RowFormat
    {
        $format = $this->requested($create);
        if ($format === RowFormat::Fixed || $this->antelope($format, $variables)) {
            return $variables->instance->version === '5.6.51' ? RowFormat::Compact : RowFormat::Dynamic;
        }
        if ($format === null || $format === RowFormat::Default) {
            return RowFormat::from(strtoupper((string) ($variables->read('innodb_default_row_format') ?? 'COMPACT')));
        }

        return $format;
    }

    /**
     * Identifies the formats unavailable under the legacy Antelope file setting.
     * MySQL 5.7 accepts DYNAMIC independently of that setting.
     */
    public function antelope(?RowFormat $format, Variables $variables): bool
    {
        return strcasecmp((string) $variables->read('innodb_file_format'), 'Antelope') === 0
            && ($format === RowFormat::Compressed || $format === RowFormat::Dynamic && $variables->instance->version === '5.6.51');
    }

    /**
     * Reports format warnings before strict-mode engine rejection.
     */
    public function refusedFormat(?RowFormat $format, Variables $variables, Context $context): bool
    {
        $antelope = $this->antelope($format, $variables);
        if ($format !== RowFormat::Fixed && !$antelope) {
            return false;
        }
        $strict = ProgramSource::enabled($variables->read('innodb_strict_mode'));
        if ($antelope) {
            $context->diagnostics->warning(SchemaError::IllegalCreateOption, 'InnoDB: ROW_FORMAT=' . $format?->value . ' requires innodb_file_format > Antelope.');
        } elseif ($strict) {
            $context->diagnostics->warning(SchemaError::IllegalCreateOption, 'InnoDB: invalid ROW_FORMAT specifier.');
        }
        if (!$strict) {
            $default = $variables->instance->version === '5.6.51' ? 'COMPACT' : 'DYNAMIC';
            $context->diagnostics->warning(SchemaError::IllegalCreateOption, "InnoDB: assuming ROW_FORMAT={$default}.");
        }

        return true;
    }

    /**
     * Identifies alterations that retain the existing physical row layout.
     * MySQL 9.1 selects instant column addition by default; earlier releases
     * retain the format for that change only with explicit ALGORITHM=INSTANT.
     *
     * @param list<int|null> $origins The old column positions, null for additions
     */
    public function retained(\MySqlMemory\Dictionary\TableDefinition $before, \MySqlMemory\Dictionary\TableDefinition $after, array $origins, TableChange $change, \SqlSemantics\Contract\GrammarRelease $release): bool
    {
        if ($change->storageLayout || $change->copies || $change->algorithm === 'copy') {
            return false;
        }
        foreach ($origins as $position => $origin) {
            if ($origin !== null && TableRebuild::changed($before->columns[$origin]->domain, $after->columns[$position]->domain)) {
                return false;
            }
        }
        if (count($origins) === count($before->columns) && !in_array(null, $origins, true)) {
            return true;
        }

        return $change->algorithm === 'instant' || $change->algorithm === null && $release === \SqlSemantics\Contract\GrammarRelease::MySql910;
    }

    /**
     * Applies the table's effective setting and release-specific temporary-table limits.
     *
     * @throws SqlError When the table cannot use the requested encryption
     */
    public function tableEncryption(CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $create, Session $session): void
    {
        $name = $create->name->name->value;
        $value = (new Database\TableEncryption())->value($create, $session);
        if ($create->temporaryWords > 0 && $value === '' && !$session->settings()->legacy()) {
            throw new SqlError(SchemaError::InvalidEncryption, SchemaError::InvalidEncryption->message(), null, [[SchemaError::IllegalHa->value, SchemaError::IllegalHa->message($name)]]);
        }
        if ($create->temporaryWords > 0 && strtoupper($value) === 'Y' && $session->settings()->legacy()) {
            throw new SqlError(SchemaError::TablespaceCannotEncrypt, SchemaError::TablespaceCannotEncrypt->message(), null, [[SchemaError::IllegalHa->value, SchemaError::IllegalHa->message($name)]]);
        }
        $this->encryption($value, $name, $session->settings()->legacy());
    }

    /**
     * Checks the encryption flag against the absence of an installed keyring.
     *
     * @param bool $legacy Whether the server reports the pre-8.0 keyring plugin wording
     *
     * @throws SqlError When the flag is invalid or requests encryption
     */
    public function encryption(string $value, string $table, bool $legacy = false): void
    {
        if (!in_array(strtoupper($value), ['', 'N', 'Y'], true)) {
            throw new SqlError(SchemaError::InvalidEncryption, SchemaError::InvalidEncryption->message(), null, [[SchemaError::IllegalHa->value, SchemaError::IllegalHa->message($table)]]);
        }
        if (strtoupper($value) === 'Y') {
            $message = AdministrationError::KeyringMissing->message();
            throw new SqlError(AdministrationError::KeyringMissing, $legacy ? str_replace('a keyring is', 'a keyring plugin is', $message) : $message, null, [[SchemaError::UnsupportedExtension->value, SchemaError::UnsupportedExtension->message($table)]]);
        }
    }
}
