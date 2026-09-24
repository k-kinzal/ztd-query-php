<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Schema\Column\Attributes;

/**
 * Writes named column attributes with identifier and literal boundaries.
 *
 * @visibility SqlSemantics
 */
final class ColumnAttributes
{
    /**
     * Writes only explicitly declared attributes; PostgreSQL takes STORAGE and COMPRESSION before the column constraints.
     */
    public static function write(Attributes $attributes, Dialect $dialect): Tree
    {
        $parts = [];
        if ($attributes->storageStrategy !== null) {
            $parts[] = Build::keyword('STORAGE ' . $attributes->storageStrategy->value);
        }
        if ($dialect === Dialect::PostgreSql && $attributes->compression !== null) {
            array_push($parts, Build::keyword('COMPRESSION'), Build::identifier([$attributes->compression], $dialect));
        }
        if ($attributes->collation !== null) {
            array_push($parts, Build::keyword('COLLATE'), Build::identifier($attributes->collation->parts, $dialect));
        }
        foreach (['CHARACTER SET' => $attributes->characterSet, 'COMPRESSION' => $dialect === Dialect::PostgreSql ? null : $attributes->compression] as $keyword => $name) {
            if ($name !== null) {
                array_push($parts, Build::keyword($keyword), Build::identifier([$name], $dialect));
            }
        }
        foreach (['COMMENT' => $attributes->comment, 'ENGINE_ATTRIBUTE' => $attributes->engineAttribute, 'SECONDARY_ENGINE_ATTRIBUTE' => $attributes->secondaryEngineAttribute] as $keyword => $value) {
            if ($value !== null) {
                array_push($parts, Build::keyword($keyword), new Atom('literal', Literal::encode($value, $dialect)[0]));
            }
        }
        foreach (['STORAGE' => $attributes->storage, 'COLUMN_FORMAT' => $attributes->format] as $keyword => $value) {
            if ($value !== null) {
                $parts[] = Build::keyword($keyword . ' ' . strtoupper($value->value));
            }
        }
        if ($attributes->visible !== null) {
            $parts[] = Build::keyword($attributes->visible ? 'VISIBLE' : 'INVISIBLE');
        }
        if ($attributes->spatialReferenceId !== null) {
            $parts[] = Build::keyword('SRID ' . $attributes->spatialReferenceId);
        }
        if ($attributes->zeroFill) {
            $parts[] = Build::keyword('ZEROFILL');
        }
        if ($attributes->binary) {
            $parts[] = Build::keyword('BINARY');
        }
        return new Tree('column-attributes', $parts);
    }
}
