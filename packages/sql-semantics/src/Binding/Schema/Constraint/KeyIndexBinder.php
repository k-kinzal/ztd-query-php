<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Constraint;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Definition\IndexReader;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Schema\OptionBinding;
use SqlSemantics\Binding\Schema\StorageParameters;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Constraint\KeyIndex;
use SqlSemantics\Schema\Index\Kind;
use SqlSemantics\Schema\Index\Properties;

/**
 * Reads the options a primary or unique key declares for its index.
 *
 * @visibility SqlSemantics
 */
final class KeyIndexBinder
{
    /**
     * Reads the MySQL index name, method and options, or the PostgreSQL INCLUDE columns, WITH parameters and USING INDEX
     * TABLESPACE; a MySQL primary key index is always named PRIMARY, so its written name is not kept.
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $source, Scope $scope, bool $primary): KeyIndex
    {
        $identifiers = $scope->identifiers;
        $parsed = (new IndexReader($identifiers, ''))->definition($source, '', null, [], []);
        $options = $parsed->options;
        $tablespace = Tree::outer($source, ['OptConsTableSpace'])[0] ?? null;
        $tablespaceTokens = $tablespace?->tokens() ?? [];
        $properties = new Properties(
            Kind::Ordinary,
            isset($options['invisible']) ? false : (isset($options['visible']) ? true : null),
            OptionBinding::integer($options, 'key_block_size'),
            OptionBinding::string($options, 'comment'),
            null,
            $tablespaceTokens === [] ? null : $identifiers->name($tablespaceTokens[count($tablespaceTokens) - 1]),
            OptionBinding::string($options, 'engine_attribute'),
            OptionBinding::string($options, 'secondary_engine_attribute'),
            true,
            $identifiers->dialect === Dialect::PostgreSql ? StorageParameters::read($source, $scope, ['a_expr', 'OptConsTableSpace'], 'def_elem') : [],
        );
        return new KeyIndex($primary ? null : self::name($source, $scope), $parsed->method, $parsed->include, $properties);
    }

    /**
     * Reads the MySQL index name written after UNIQUE [KEY | INDEX], before any USING or TYPE clause; releases before 8.0 write it as a plain identifier.
     */
    public static function name(Node $source, Scope $scope): ?string
    {
        $candidates = [...Tree::outer($source, ['opt_index_name_and_type']), Tree::child($source, ['opt_ident']) ?? new Node('opt_ident', 0, [])];
        $tokens = (array_values(array_filter($candidates, Tree::hasTokens(...)))[0] ?? null)?->tokens() ?? [];
        return $tokens === [] || in_array(strtoupper($tokens[0]->text), ['USING', 'TYPE'], true) ? null : $scope->identifiers->name($tokens[0]);
    }
}
