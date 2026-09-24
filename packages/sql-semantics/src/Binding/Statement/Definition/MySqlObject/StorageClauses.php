<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlObject;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\MySqlNumbers;
use SqlSemantics\Binding\Statement\Definition\Storage\RemovalOptions;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Definition\Storage\LogfileGroupOptions;
use SqlSemantics\Model\Definition\Storage\StorageEncryption;
use SqlSemantics\Model\Definition\Storage\TablespaceChanges;
use SqlSemantics\Model\Definition\Storage\TablespaceOptions;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads the tablespace and logfile-group option clauses of every MySQL release into their final values.
 * @visibility SqlSemantics
 */
final class StorageClauses
{
    /**
     * Grammar rules of the size, node group, comment, encryption, and engine attribute options in MySQL 5.x and 8+.
     */
    public const RULES = [
        'ts_option_initial_size', 'ts_option_autoextend_size', 'ts_option_max_size', 'ts_option_extent_size', 'ts_option_undo_buffer_size', 'ts_option_redo_buffer_size',
        'ts_option_nodegroup', 'ts_option_comment', 'ts_option_file_block_size', 'ts_option_encryption', 'ts_option_engine_attribute',
        'opt_ts_initial_size', 'opt_ts_autoextend_size', 'opt_ts_max_size', 'opt_ts_extent_size', 'opt_ts_undo_buffer_size', 'opt_ts_redo_buffer_size',
        'opt_ts_nodegroup', 'opt_ts_comment', 'opt_ts_file_block_size',
    ];

    /**
     * Keeps the last value of each repeatable option, as the server does.
     * @param array<string, int> $numbers Sizes in bytes and the node group, keyed by option keyword
     */
    public function __construct(
        public readonly array $numbers,
        public readonly ?string $comment,
        public readonly ?StorageEncryption $encryption,
        public readonly ?string $engineAttribute,
        public readonly ?string $engine,
        public readonly CompletionWait $waiting,
    ) {
    }

    /**
     * Rejects repeated NODEGROUP, COMMENT, FILE_BLOCK_SIZE and engine options and values the server cannot use.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function read(Node $source, Identifiers $identifiers): self
    {
        $numbers = [];
        $texts = [];
        foreach (Tree::outer($source, self::RULES) as $option) {
            $keyword = strtoupper($option->tokens()[0]->text ?? '');
            if (in_array($keyword, ['NODEGROUP', 'COMMENT', 'FILE_BLOCK_SIZE'], true) && (isset($numbers[$keyword]) || isset($texts[$keyword]))) {
                throw new InvalidSql(InputViolation::StorageValue, $option);
            }
            if (in_array($keyword, ['COMMENT', 'ENCRYPTION', 'ENGINE_ATTRIBUTE'], true)) {
                $texts[$keyword] = MySqlNames::read((Tree::outer($option, ['TEXT_STRING_sys'])[0] ?? throw new UnclassifiedSql('A storage text option requires its string.'))->tokens()[0], $identifiers);
            } elseif ($keyword === 'NODEGROUP') {
                $numbers[$keyword] = MySqlNumbers::read(Tree::outer($option, ['real_ulong_num'])[0] ?? throw new UnclassifiedSql('NODEGROUP requires its number.'), InputViolation::StorageValue, true);
            } else {
                $numbers[$keyword] = self::size(Tree::outer($option, ['size_number'])[0] ?? throw new UnclassifiedSql('A storage size option requires its size.'), $identifiers);
            }
        }
        $attribute = $texts['ENGINE_ATTRIBUTE'] ?? null;
        if ($attribute !== null && $attribute !== '' && json_decode($attribute) === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidSql(InputViolation::StorageValue, $source);
        }
        return new self($numbers, $texts['COMMENT'] ?? null, self::encryption($texts['ENCRYPTION'] ?? null, $source), $attribute, RemovalOptions::engine($source, $identifiers), RemovalOptions::waiting($source));
    }

    /**
     * Reads a byte count written as an unsigned integer or as fewer than 2^31 kilobytes, megabytes, or gigabytes.
     * @throws InvalidSql
     */
    public static function size(Node $size, Identifiers $identifiers): int
    {
        if (Tree::child($size, ['real_ulonglong_num']) !== null) {
            return MySqlNumbers::read($size, InputViolation::StorageValue, true);
        }
        if (preg_match('/^([0-9]*)([kmg])$/iD', $identifiers->name($size->tokens()[0]), $match) !== 1) {
            throw new InvalidSql(InputViolation::StorageValue, $size);
        }
        $digits = ltrim($match[1], '0');
        if (strlen($digits) > 10 || (int) $digits >= 2 ** 31) {
            throw new InvalidSql(InputViolation::StorageValue, $size);
        }
        return (int) $digits << (10 * ((int) strpos('kmg', strtolower($match[2])) + 1));
    }

    /**
     * Decodes the Y or N encryption request case-insensitively.
     * @throws InvalidSql
     */
    public static function encryption(?string $text, Node $source): ?StorageEncryption
    {
        return $text === null ? null : (StorageEncryption::tryFrom(strtoupper($text)) ?? throw new InvalidSql(InputViolation::StorageValue, $source));
    }

    /**
     * Returns the final value of a size or node group option.
     */
    public function number(string $keyword): ?int
    {
        return $this->numbers[$keyword] ?? null;
    }

    /**
     * Builds the initial properties of a new tablespace.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function tablespace(): TablespaceOptions
    {
        return new TablespaceOptions($this->number('INITIAL_SIZE'), $this->number('AUTOEXTEND_SIZE'), $this->number('MAX_SIZE'), $this->number('EXTENT_SIZE'), $this->number('FILE_BLOCK_SIZE'), $this->number('NODEGROUP'), $this->engine, $this->comment, $this->encryption, $this->engineAttribute, $this->waiting);
    }

    /**
     * Builds the property changes of a tablespace alteration.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function changes(): TablespaceChanges
    {
        return new TablespaceChanges($this->number('INITIAL_SIZE'), $this->number('AUTOEXTEND_SIZE'), $this->number('MAX_SIZE'), $this->engine, $this->encryption, $this->engineAttribute, $this->waiting);
    }

    /**
     * Builds the initial properties of a new logfile group.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function logfileGroup(): LogfileGroupOptions
    {
        return new LogfileGroupOptions($this->number('INITIAL_SIZE'), $this->number('UNDO_BUFFER_SIZE'), $this->number('REDO_BUFFER_SIZE'), $this->number('NODEGROUP'), $this->engine, $this->comment, $this->waiting);
    }
}
