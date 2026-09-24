<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Schema\Table;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes classified table properties and declared storage parameters.
 *
 * @visibility SqlSemantics
 */
final class Storage
{
    /**
     * @param list<\SqlSemantics\Schema\Storage\Parameter> $parameters
     */
    public static function parameters(array $parameters, Dialect $dialect): Tree
    {
        return Build::separated(array_map(static fn ($parameter): Tree => new Tree('parameter', [Build::identifier($parameter->name->parts, $dialect), ...($parameter->value instanceof \SqlSemantics\Schema\Storage\ImpliedSetting ? [] : [Build::keyword('='), Expressions::write($parameter->value)])]), $parameters));
    }

    /**
     * Writes table suffix properties.
     */
    public static function table(?Table\Properties $properties, Dialect $dialect): Tree
    {
        if ($properties instanceof Table\SqliteProperties) {
            return Build::separated([...($properties->withoutRowId ? [Build::keyword('WITHOUT ROWID')] : []), ...($properties->strict ? [Build::keyword('STRICT')] : [])]);
        }
        if ($properties instanceof Table\PostgreSqlProperties) {
            return new Tree('table-properties', [
                ...($properties->parents === [] ? [] : [Build::keyword('INHERITS'), Build::parentheses(Build::separated(array_map(static fn (\SqlSemantics\Model\Relation\QualifiedName $parent): Tree => Build::identifier($parent->parts, $dialect), $properties->parents)))]),
                ...($properties->partitioning === null ? [] : [self::partitioning($properties->partitioning, $dialect)]),
                ...($properties->accessMethod === null ? [] : [Build::keyword('USING'), Build::identifier([$properties->accessMethod], $dialect)]),
                ...($properties->storageParameters === [] ? [] : [Build::keyword('WITH'), Build::parentheses(self::parameters($properties->storageParameters, $dialect))]),
                ...($properties->onCommit === Table\CommitAction::PreserveRows ? [] : [Build::keyword($properties->onCommit === Table\CommitAction::Drop ? 'ON COMMIT DROP' : 'ON COMMIT DELETE ROWS')]),
                ...($properties->tablespace === null ? [] : [Build::keyword('TABLESPACE'), Build::identifier([$properties->tablespace], $dialect)]),
            ]);
        }
        return $properties instanceof Table\MySqlProperties ? self::mysql($properties, $dialect) : new Tree('table-properties', []);
    }

    /**
     * Writes PARTITION BY; a column key stays bare and any other key is parenthesized.
     */
    public static function partitioning(\SqlSemantics\Schema\Partition\PartitionScheme $scheme, Dialect $dialect): Tree
    {
        $keys = array_map(static fn (\SqlSemantics\Schema\Partition\PartitionKey $key): Tree => new Tree('partition-key', [
            $key->value instanceof \SqlSemantics\Model\Scalar\Reference\ColumnReference || $key->value instanceof \SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference ? Expressions::write($key->value) : Build::parentheses(Expressions::write($key->value)),
            ...($key->collation === null ? [] : [Build::keyword('COLLATE'), Build::identifier($key->collation->parts, $dialect)]),
            ...($key->operatorClass === null ? [] : [Build::identifier($key->operatorClass->parts, $dialect)]),
        ]), $scheme->keys);
        return new Tree('partitioning', [Build::keyword('PARTITION BY ' . $scheme->strategy->value), Build::parentheses(Build::separated($keys))]);
    }

    /**
     * Writes named MySQL storage options.
     */
    public static function mysql(Table\MySqlProperties $properties, Dialect $dialect): Tree
    {
        $parts = [];
        foreach (['ENGINE' => $properties->engine, 'CHARACTER SET' => $properties->characterSet, 'COLLATE' => $properties->collation, 'TABLESPACE' => $properties->tablespace] as $keyword => $name) {
            if ($name !== null) {
                array_push($parts, Build::keyword($keyword), Build::identifier([$name], $dialect));
            }
        }
        foreach (['COMMENT' => $properties->comment, 'COMPRESSION' => $properties->compression, 'ENCRYPTION' => $properties->encryption, 'CONNECTION' => $properties->connection, 'DATA DIRECTORY' => $properties->dataDirectory, 'INDEX DIRECTORY' => $properties->indexDirectory, 'PASSWORD' => $properties->password, 'ENGINE_ATTRIBUTE' => $properties->engineAttribute, 'SECONDARY_ENGINE_ATTRIBUTE' => $properties->secondaryEngineAttribute] as $keyword => $value) {
            if ($value !== null) {
                array_push($parts, Build::keyword($keyword . ' ='), new Atom('literal', Literal::encode($value, $dialect)[0]));
            }
        }
        foreach (['AUTO_INCREMENT' => $properties->autoIncrement, 'AVG_ROW_LENGTH' => $properties->averageRowLength, 'CHECKSUM' => $properties->checksum, 'DELAY_KEY_WRITE' => $properties->delayKeyWrite, 'KEY_BLOCK_SIZE' => $properties->keyBlockSize, 'MAX_ROWS' => $properties->maxRows, 'MIN_ROWS' => $properties->minRows, 'STATS_SAMPLE_PAGES' => $properties->statsSamplePages, 'PACK_KEYS' => $properties->packKeys, 'STATS_AUTO_RECALC' => $properties->statsAutoRecalc, 'STATS_PERSISTENT' => $properties->statsPersistent] as $keyword => $value) {
            if ($value !== null) {
                $parts[] = Build::keyword($keyword . ' = ' . (int) $value);
            }
        }
        if ($properties->rowFormat !== null) {
            $parts[] = Build::keyword('ROW_FORMAT = ' . strtoupper($properties->rowFormat->value));
        }
        $parts[] = MySqlTable\TableOptionWriter::write($properties);
        return new Tree('table-properties', $parts);
    }
}
