<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Schema\Table\MySqlProperties;
use SqlSemantics\Schema\Table\RowFormat;

/**
 * Converts MySQL table options to named, typed properties.
 *
 * @visibility SqlSemantics
 */
final class MySqlTableProperties
{
    /**
     * @param array<string, string|bool|list<string>> $options
     */
    public static function bind(array $options): MySqlProperties
    {
        OptionBinding::classified($options, ['temporary', 'if_not_exists', 'row_format', 'engine', 'character_set', 'collation', 'comment', 'compression', 'encryption', 'connection', 'data_directory', 'index_directory', 'password', 'tablespace', 'engine_attribute', 'secondary_engine_attribute', 'auto_increment', 'avg_row_length', 'checksum', 'delay_key_write', 'key_block_size', 'max_rows', 'min_rows', 'stats_sample_pages', 'pack_keys', 'stats_auto_recalc', 'stats_persistent']);
        return new MySqlProperties(
            temporary: isset($options['temporary']),
            rowFormat: ($format = OptionBinding::string($options, 'row_format')) === null ? null : RowFormat::from(strtolower($format)),
            engine: OptionBinding::string($options, 'engine'),
            characterSet: OptionBinding::string($options, 'character_set'),
            collation: OptionBinding::string($options, 'collation'),
            comment: OptionBinding::string($options, 'comment'),
            compression: OptionBinding::string($options, 'compression'),
            encryption: OptionBinding::string($options, 'encryption'),
            connection: OptionBinding::string($options, 'connection'),
            dataDirectory: OptionBinding::string($options, 'data_directory'),
            indexDirectory: OptionBinding::string($options, 'index_directory'),
            password: OptionBinding::string($options, 'password'),
            tablespace: OptionBinding::string($options, 'tablespace'),
            engineAttribute: OptionBinding::string($options, 'engine_attribute'),
            secondaryEngineAttribute: OptionBinding::string($options, 'secondary_engine_attribute'),
            autoIncrement: OptionBinding::integer($options, 'auto_increment'),
            averageRowLength: OptionBinding::integer($options, 'avg_row_length'),
            checksum: OptionBinding::integer($options, 'checksum'),
            delayKeyWrite: OptionBinding::integer($options, 'delay_key_write'),
            keyBlockSize: OptionBinding::integer($options, 'key_block_size'),
            maxRows: OptionBinding::integer($options, 'max_rows'),
            minRows: OptionBinding::integer($options, 'min_rows'),
            statsSamplePages: OptionBinding::integer($options, 'stats_sample_pages'),
            packKeys: self::boolean($options, 'pack_keys'),
            statsAutoRecalc: self::boolean($options, 'stats_auto_recalc'),
            statsPersistent: self::boolean($options, 'stats_persistent'),
        );
    }

    /**
     * @param array<string, string|bool|list<string>> $options
     * @throws UnclassifiedSql
     */
    public static function boolean(array $options, string $name): ?bool
    {
        return match (strtoupper(OptionBinding::string($options, $name) ?? 'DEFAULT')) {
            'DEFAULT' => null,
            '0' => false,
            '1' => true,
            default => throw new UnclassifiedSql('Expected a boolean table option: ' . $name),
        };
    }
}
