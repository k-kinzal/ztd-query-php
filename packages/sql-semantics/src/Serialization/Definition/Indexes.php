<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Schema\Index;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes index keys and declaration options without consulting source syntax.
 *
 * @visibility SqlSemantics
 */
final class Indexes
{
    /**
     * Writes table-local keys and indexes.
     */
    public static function inline(IndexDefinition $index, Dialect $dialect): Tree
    {
        return new Tree('index', [Build::keyword(self::kind($index) . 'INDEX'), ...($index->name === null ? [] : [Build::identifier([$index->name], $dialect)]), ...($index->method === null ? [] : [Build::keyword('USING'), $dialect === Dialect::MySql ? Build::keyword(strtoupper($index->method)) : Build::identifier([$index->method], $dialect)]), self::keys($index, $dialect), self::options($index->properties, $dialect)]);
    }

    /**
     * Returns the explicitly classified index kind.
     */
    public static function kind(IndexDefinition $index): string
    {
        return match ($index->properties->kind) {
            Index\Kind::Ordinary => $index->unique ? 'UNIQUE ' : '',
            Index\Kind::FullText => 'FULLTEXT ',
            Index\Kind::Spatial => 'SPATIAL ',
        };
    }

    /**
     * Writes keys, included columns and partial-index predicates.
     */
    public static function keys(IndexDefinition $index, Dialect $dialect): Tree
    {
        return new Tree('index-keys', [Build::parentheses(Build::separated(array_map(static fn ($key): Tree => IndexKeys::write($key, $dialect), $index->elements))), ...($index->include === [] ? [] : [Build::keyword('INCLUDE'), Constraints::columns($index->include, $dialect)]), ...($index->properties->nullsDistinct ? [] : [Build::keyword('NULLS NOT DISTINCT')]), ...($index->predicate === null ? [] : [Build::keyword('WHERE'), Expressions::write($index->predicate)])]);
    }

    /**
     * Writes index storage and visibility options.
     */
    public static function options(Index\Properties $properties, Dialect $dialect): Tree
    {
        $parts = [];
        if ($properties->visible !== null) {
            $parts[] = Build::keyword($properties->visible ? 'VISIBLE' : 'INVISIBLE');
        }
        if ($properties->keyBlockSize !== null) {
            $parts[] = Build::keyword('KEY_BLOCK_SIZE = ' . $properties->keyBlockSize);
        }
        foreach (['COMMENT' => $properties->comment, 'ENGINE_ATTRIBUTE' => $properties->engineAttribute, 'SECONDARY_ENGINE_ATTRIBUTE' => $properties->secondaryEngineAttribute] as $keyword => $value) {
            if ($value !== null) {
                array_push($parts, Build::keyword($keyword), new Atom('literal', Literal::encode($value, $dialect)[0]));
            }
        }
        if ($properties->parser !== null) {
            array_push($parts, Build::keyword('WITH PARSER'), Build::identifier($properties->parser->parts, $dialect));
        }
        if ($properties->storageParameters !== []) {
            array_push($parts, Build::keyword('WITH'), Build::parentheses(Storage::parameters($properties->storageParameters, $dialect)));
        }
        if ($properties->tablespace !== null) {
            array_push($parts, Build::keyword('TABLESPACE'), Build::identifier([$properties->tablespace], $dialect));
        }
        return new Tree('index-properties', $parts);
    }
}
