<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Database;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Database\DatabaseCharacterSet;
use SqlSemantics\Model\Definition\Database\DatabaseCollation;
use SqlSemantics\Model\Definition\Database\DatabaseEncryption;
use SqlSemantics\Model\Definition\Database\DatabaseReadOnly;
use SqlSemantics\Model\Definition\Database\ServerCharacterInheritance;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads explicitly classified database defaults and access requests in source order.
 * @visibility SqlSemantics
 */
final class DatabaseOptions
{
    /**
     * @return list<DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption> Initial database defaults
     */
    public static function creation(Node $source, Identifiers $identifiers): array
    {
        return array_map(static fn (Node $option) => self::initial($option, $identifiers), Tree::outer($source, ['create_database_option']));
    }

    /**
     * @return non-empty-list<DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption|DatabaseReadOnly> Changes to existing defaults
     * @throws InvalidSql
     */
    public static function alteration(Node $source, Identifiers $identifiers): array
    {
        $options = [];
        $readOnly = null;
        foreach (Tree::outer($source, ['create_database_option', 'ternary_option']) as $option) {
            if ($option->name !== 'ternary_option') {
                $options[] = self::initial($option, $identifiers);
                continue;
            }
            $access = DatabaseOperands::readOnly($option);
            if ($readOnly !== null && $readOnly !== $access) {
                throw new InvalidSql(InputViolation::DatabaseReadOnly, $option);
            }
            $options[] = $readOnly = $access;
        }
        return Collections::nonEmpty($options);
    }

    /**
     * Reads only creation-legal option roles; READ ONLY has a separate alteration path.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function initial(Node $option, Identifiers $identifiers): DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption
    {
        $charset = Tree::child($option, ['default_charset']);
        $collation = Tree::child($option, ['default_collation']);
        if ($charset !== null || $collation !== null) {
            $part = $charset ?? $collation;
            $name = Tree::outer($part, ['charset_name_or_default', 'collation_name_or_default', 'charset_name', 'collation_name'])[0] ?? throw new UnclassifiedSql('A database character default requires its name.');
            $token = $name->tokens()[0];
            $value = in_array($token->name, ['DEFAULT', 'DEFAULT_SYM'], true) ? ServerCharacterInheritance::Inherit : DatabaseOperands::name($token, $identifiers);
            if ($value === '') {
                throw new InvalidSql(InputViolation::DatabaseCharacterName, $name);
            }
            return $charset !== null ? new DatabaseCharacterSet($value) : new DatabaseCollation($value);
        }
        $encryption = Tree::child($option, ['default_encryption']);
        if ($encryption !== null) {
            return DatabaseOperands::encryption($encryption, $identifiers);
        }
        throw new UnclassifiedSql('Unclassified database default: ' . Tree::text($option));
    }
}
