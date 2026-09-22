<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query\Document;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\TableFunction\Xml;

/**
 * Binds XMLTABLE paths, namespaces, passing modes, defaults, and null constraints.
 * @visibility SqlSemantics
 */
final class XmlTableBinder
{
    /**
     * Retains row expansion separately from ordinary scalar function calls.
     */
    public static function bind(Node $source, Scope $scope): Xml\XmlTable
    {
        $row = Tree::child($source, ['c_expr']);
        $passing = Tree::child($source, ['xmlexists_argument']);
        $document = $passing === null ? null : Tree::child($passing, ['c_expr']);
        if ($row === null || $document === null) {
            Tree::invalid($source, 'XMLTABLE document and row path');
        }
        $modes = Tree::outer($passing, ['xml_passing_mech']);
        $namespaces = Tree::outer($source, ['xml_namespace_el']);
        return new Xml\XmlTable((new ExpressionBinder())->bind($document, $scope), (new ExpressionBinder())->bind($row, $scope), array_map(static fn (Node $column): Xml\Ordinality|Xml\ValueColumn => self::column($column, $scope), Tree::outer($source, ['xmltable_column_el'])), array_map(static fn (Node $namespace): Xml\NamespaceBinding => self::namespace($namespace, $scope), $namespaces), isset($modes[0]) ? Xml\PassingMode::from(strtoupper(Tree::text($modes[0]))) : Xml\PassingMode::Default, isset($modes[1]) ? Xml\PassingMode::from(strtoupper(Tree::text($modes[1]))) : Xml\PassingMode::Default);
    }

    /**
     * A namespace prefix is absent only for an explicit default namespace declaration.
     */
    public static function namespace(Node $source, Scope $scope): Xml\NamespaceBinding
    {
        $uri = Tree::child($source, ['b_expr']);
        if ($uri === null) {
            Tree::invalid($source, 'XML namespace URI');
        }
        $prefix = Tree::child($source, ['ColLabel']);
        return new Xml\NamespaceBinding((new ExpressionBinder())->bind($uri, $scope), $prefix === null ? null : $scope->identifiers->parts($prefix)[0]);
    }

    /**
     * Requires a storage type for extracted values and no path for ordinal columns.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function column(Node $source, Scope $scope): Xml\Ordinality|Xml\ValueColumn
    {
        $label = Tree::child($source, ['ColId']);
        if ($label === null) {
            Tree::invalid($source, 'XMLTABLE column name');
        }
        $name = $scope->identifiers->parts($label)[0];
        $type = Tree::child($source, ['Typename']);
        if ($type === null) {
            return new Xml\Ordinality($name);
        }
        $path = null;
        $default = null;
        $notNull = false;
        $seen = [];
        foreach (Tree::outer($source, ['xmltable_column_option_el']) as $option) {
            $word = strtoupper($option->tokens()[0]->text);
            $domain = in_array($word, ['NULL', 'NOT'], true) ? 'nullability' : $word;
            if (isset($seen[$domain]) || !in_array($word, ['PATH', 'DEFAULT', 'NOT', 'NULL'], true)) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::XmlOption, $option);
            }
            $seen[$domain] = true;
            $value = Tree::child($option, ['b_expr']);
            if ($word === 'PATH' && $value !== null) {
                $path = (new ExpressionBinder())->bind($value, $scope);
            } elseif ($word === 'DEFAULT' && $value !== null) {
                $default = (new ExpressionBinder())->bind($value, $scope);
            } else {
                $notNull = $word === 'NOT';
            }
        }
        return new Xml\ValueColumn($name, (new \SqlSemantics\Ast\TypeReader($scope->identifiers->dialect))->read($type), $path, $default, $notNull);
    }
}
