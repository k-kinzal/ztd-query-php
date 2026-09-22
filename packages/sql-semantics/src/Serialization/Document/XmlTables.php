<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Document;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\TableFunction\Xml;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes XMLTABLE namespace bindings, row selection, column paths, and defaults.
 * @visibility SqlSemantics
 */
final class XmlTables
{
    /**
     * Serializes a document expansion without flattening its column declarations.
     */
    public static function write(Xml\XmlTable $table, Dialect $dialect): Tree
    {
        $parts = [];
        if ($table->namespaces !== []) {
            array_push($parts, Build::keyword('XMLNAMESPACES'), Build::parentheses(Build::separated(array_map(static fn (Xml\NamespaceBinding $namespace): Tree => self::namespace($namespace, $dialect), $table->namespaces))), new Atom('punctuation', ','));
        }
        array_push($parts, ...[Expressions::write($table->rowPath), Build::keyword('PASSING'), ...($table->inputMode === Xml\PassingMode::Default ? [] : [Build::keyword($table->inputMode->value)]), Expressions::write($table->document), ...($table->outputMode === Xml\PassingMode::Default ? [] : [Build::keyword($table->outputMode->value)]), Build::keyword('COLUMNS'), Build::separated(array_map(static fn (Xml\Ordinality|Xml\ValueColumn $column): Tree => self::column($column, $dialect), $table->columns))]);
        return new Tree('xml-table', [Build::keyword('XMLTABLE'), Build::parentheses(new Tree('document-rows', $parts))]);
    }

    /**
     * Quotes namespace prefixes independently of namespace URI expressions.
     */
    public static function namespace(Xml\NamespaceBinding $namespace, Dialect $dialect): Tree
    {
        return $namespace->prefix === null ? new Tree('default-namespace', [Build::keyword('DEFAULT'), Expressions::write($namespace->uri)]) : new Tree('namespace', [Expressions::write($namespace->uri), Build::keyword('AS'), Build::identifier([$namespace->prefix], $dialect)]);
    }

    /**
     * Ordinality has no path, storage type, or default-expression payload.
     */
    public static function column(Xml\Ordinality|Xml\ValueColumn $column, Dialect $dialect): Tree
    {
        $name = Build::identifier([$column->name], $dialect);
        if ($column instanceof Xml\Ordinality) {
            return new Tree('ordinality', [$name, Build::keyword('FOR ORDINALITY')]);
        }
        return new Tree('xml-column', [$name, TypeDeclaration::write($column->type), ...($column->path === null ? [] : [Build::keyword('PATH'), Expressions::write($column->path)]), ...($column->default === null ? [] : [Build::keyword('DEFAULT'), Expressions::write($column->default)]), ...($column->notNull ? [Build::keyword('NOT NULL')] : [])]);
    }
}
