<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Replication\ReplicationText;
use SqlSemantics\Model\Definition\MySqlTable\Partition\TablePartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableOptionReset;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Table;

/**
 * Reads MySQL table options by keyword; a later option replaces an earlier one, and DEFAULT or NULL resets an option.
 * @visibility SqlSemantics
 */
final class TableOptions
{
    /**
     * @var array<string, string>
     */
    public const KEYS = [
        'ENGINE' => 'engine', 'SECONDARY_ENGINE' => 'secondary_engine', 'MAX_ROWS' => 'max_rows', 'MIN_ROWS' => 'min_rows',
        'AVG_ROW_LENGTH' => 'avg_row_length', 'PASSWORD' => 'password', 'COMMENT' => 'comment', 'COMPRESSION' => 'compression',
        'ENCRYPTION' => 'encryption', 'AUTO_INCREMENT' => 'auto_increment', 'PACK_KEYS' => 'pack_keys', 'STATS_AUTO_RECALC' => 'stats_auto_recalc',
        'STATS_PERSISTENT' => 'stats_persistent', 'STATS_SAMPLE_PAGES' => 'stats_sample_pages', 'CHECKSUM' => 'checksum', 'TABLE_CHECKSUM' => 'checksum',
        'DELAY_KEY_WRITE' => 'delay_key_write', 'ROW_FORMAT' => 'row_format', 'UNION' => 'union', 'CHARACTER' => 'character_set', 'CHARSET' => 'character_set',
        'COLLATE' => 'collation', 'INSERT_METHOD' => 'insert_method', 'DATA' => 'data_directory', 'INDEX' => 'index_directory', 'TABLESPACE' => 'tablespace',
        'STORAGE' => 'storage', 'CONNECTION' => 'connection', 'KEY_BLOCK_SIZE' => 'key_block_size', 'START' => 'start_transaction',
        'ENGINE_ATTRIBUTE' => 'engine_attribute', 'SECONDARY_ENGINE_ATTRIBUTE' => 'secondary_engine_attribute', 'AUTOEXTEND_SIZE' => 'autoextend_size',
    ];

    /**
     * @var array<string, TableOptionReset>
     */
    public const RESETS = [
        'pack_keys' => TableOptionReset::PackKeys, 'stats_auto_recalc' => TableOptionReset::StatsAutoRecalc, 'stats_persistent' => TableOptionReset::StatsPersistent,
        'stats_sample_pages' => TableOptionReset::StatsSamplePages, 'secondary_engine' => TableOptionReset::SecondaryEngine,
        'character_set' => TableOptionReset::CharacterSet, 'collation' => TableOptionReset::Collation,
    ];

    /**
     * Reads every option node into typed properties and the options reset to their defaults.
     * @param list<Node> $options create_table_option nodes in SQL order
     * @return array{Table\MySqlProperties, list<TableOptionReset>}
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(array $options, Identifiers $identifiers, bool $temporary = false, ?TablePartitioning $partitioning = null): array
    {
        $values = [];
        $resets = [];
        foreach ($options as $option) {
            $words = array_map(static fn (Token $token): string => strtoupper($token->text), $option->tokens());
            $key = self::KEYS[$words[0] === 'DEFAULT' && count($words) > 2 ? $words[1] : $words[0]] ?? throw new UnclassifiedSql('Unclassified MySQL table option: ' . Tree::text($option));
            $value = $option->tokens()[count($option->tokens()) - 1];
            unset($values[$key], $resets[$key]);
            if (isset(self::RESETS[$key]) && in_array(strtoupper($value->text), ['DEFAULT', 'NULL'], true) && !str_starts_with($value->text, '`')) {
                $resets[$key] = self::RESETS[$key];
                continue;
            }
            $values[$key] = $key === 'union' ? $option : $value;
        }
        return [self::properties($values, $identifiers, $temporary, $partitioning), array_values($resets)];
    }

    /**
     * Converts the last value written for each option.
     * @param array<string, Node|Token> $values
     * @throws InvalidSql
     */
    public static function properties(array $values, Identifiers $identifiers, bool $temporary, ?TablePartitioning $partitioning): Table\MySqlProperties
    {
        $text = static fn (string $key): ?string => isset($values[$key]) ? self::text($values[$key], $identifiers) : null;
        $number = static fn (string $key): ?int => isset($values[$key]) ? MySqlNumbers::read($values[$key], InputViolation::TableOption) : null;
        return new Table\MySqlProperties(
            $temporary,
            $text('engine'),
            $text('character_set'),
            $text('collation'),
            $text('comment'),
            $text('compression'),
            $text('encryption'),
            $text('connection'),
            $text('data_directory'),
            $text('index_directory'),
            $text('password'),
            $text('tablespace'),
            $text('engine_attribute'),
            $text('secondary_engine_attribute'),
            $number('auto_increment'),
            $number('avg_row_length'),
            $number('checksum'),
            $number('delay_key_write'),
            $number('key_block_size'),
            $number('max_rows'),
            $number('min_rows'),
            ($format = $text('row_format')) === null ? null : Table\RowFormat::from(strtolower($format)),
            self::switch($values['pack_keys'] ?? null),
            self::switch($values['stats_auto_recalc'] ?? null),
            self::switch($values['stats_persistent'] ?? null),
            self::pages($values['stats_sample_pages'] ?? null),
            ($storage = $text('storage')) === null ? null : Table\TableStorage::from(strtoupper($storage)),
            $text('secondary_engine'),
            ($method = $text('insert_method')) === null ? null : Table\MergeInsertMethod::from(strtoupper($method)),
            isset($values['union']) ? self::union($values['union'], $identifiers) : null,
            isset($values['start_transaction']),
            isset($values['autoextend_size']) ? self::size($values['autoextend_size']) : null,
            $partitioning,
        );
    }

    /**
     * Decodes a quoted string, a quoted identifier, or a bare word.
     */
    public static function text(Node|Token $value, Identifiers $identifiers): string
    {
        $token = $value instanceof Token ? $value : $value->tokens()[0];
        return in_array($token->text[0] ?? '', ["'", '"'], true) ? ReplicationText::decode($token->text) : $identifiers->name($token);
    }

    /**
     * Reads a 0 or 1 switch.
     * @throws InvalidSql
     */
    public static function switch(Node|Token|null $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        return match (MySqlNumbers::read($value, InputViolation::TableOption)) {
            0 => false,
            1 => true,
            default => throw new InvalidSql(InputViolation::TableOption, $value),
        };
    }

    /**
     * Reads a sampled page count between 1 and 65535.
     * @throws InvalidSql
     */
    public static function pages(Node|Token|null $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $pages = MySqlNumbers::read($value, InputViolation::TableOption);
        if ($pages < 1 || $pages > 65535) {
            throw new InvalidSql(InputViolation::TableOption, $value);
        }
        return $pages;
    }

    /**
     * Reads a byte size written as a number or with a K, M, G, or T suffix.
     * @throws InvalidSql
     */
    public static function size(Node|Token $value): int
    {
        $token = $value instanceof Token ? $value : $value->tokens()[0];
        if (preg_match('/^([0-9]+)([kmgt])$/iD', $token->text, $match) !== 1) {
            return MySqlNumbers::read($value, InputViolation::TableOption, true);
        }
        $size = MySqlNumbers::bounded(ltrim($match[1], '0') === '' ? '0' : ltrim($match[1], '0'), strlen($match[1]) > 19, $value, InputViolation::TableOption);
        $shift = 10 * ((int) strpos('kmgt', strtolower($match[2])) + 1);
        if ($size > (PHP_INT_MAX >> $shift)) {
            throw new InvalidSql(InputViolation::TableOption, $value);
        }
        return $size << $shift;
    }

    /**
     * Reads the tables of a MERGE table's UNION option.
     * @return list<QualifiedName>
     */
    public static function union(Node|Token $option, Identifiers $identifiers): array
    {
        return $option instanceof Node ? array_map(static fn (Node $table): QualifiedName => new QualifiedName($identifiers->parts($table)), Tree::outer($option, ['table_ident'])) : [];
    }
}
