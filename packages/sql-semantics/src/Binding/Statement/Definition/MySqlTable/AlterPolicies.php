<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\IndexAlgorithm;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Definition\MySqlTable\TableAlgorithm;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads MySQL ALGORITHM and LOCK requests; the last request of each kind applies.
 * @visibility SqlSemantics
 */
final class AlterPolicies
{
    /**
     * Reads the table algorithm, DEFAULT when none is written.
     * @throws InvalidSql
     */
    public static function algorithm(Node $source, Identifiers $identifiers): TableAlgorithm
    {
        $option = self::last($source, 'alter_algorithm_option');
        return $option === null ? TableAlgorithm::Default : (TableAlgorithm::tryFrom(self::word($option, $identifiers)) ?? throw new InvalidSql(InputViolation::AlterAlgorithm, $option));
    }

    /**
     * Reads the lock level, DEFAULT when none is written.
     * @throws InvalidSql
     */
    public static function lock(Node $source, Identifiers $identifiers): IndexLock
    {
        $option = self::last($source, 'alter_lock_option');
        return $option === null ? IndexLock::Default : (IndexLock::tryFrom(self::word($option, $identifiers)) ?? throw new InvalidSql(InputViolation::AlterLock, $option));
    }

    /**
     * Reads an index algorithm; INSTANT does not apply to index creation.
     * @throws InvalidSql
     */
    public static function indexAlgorithm(Node $source, Identifiers $identifiers): IndexAlgorithm
    {
        $option = self::last($source, 'alter_algorithm_option');
        return $option === null ? IndexAlgorithm::Default : (IndexAlgorithm::tryFrom(self::word($option, $identifiers)) ?? throw new InvalidSql(InputViolation::AlterAlgorithm, $option));
    }

    /**
     * Returns the last option of a kind.
     */
    public static function last(Node $source, string $name): ?Node
    {
        $options = $source->find($name);
        return $options === [] ? null : $options[count($options) - 1];
    }

    /**
     * Reads the requested value as an uppercase word.
     */
    public static function word(Node $option, Identifiers $identifiers): string
    {
        $tokens = $option->tokens();
        $value = $tokens[count($tokens) - 1];
        return strtoupper(strtoupper($value->text) === 'DEFAULT' ? 'DEFAULT' : $identifiers->name($value));
    }
}
