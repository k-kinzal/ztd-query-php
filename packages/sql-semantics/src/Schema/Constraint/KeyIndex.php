<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Constraint;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Index\Kind;
use SqlSemantics\Schema\Index\Properties;

/**
 * The index that enforces a primary or unique key, as the key declares it: the MySQL index name, access method and
 * index options, or the PostgreSQL INCLUDE columns, WITH storage parameters and USING INDEX TABLESPACE.
 *
 * @visibility public
 * @example Reading the index options of a MySQL unique key
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build("CREATE TABLE t(a INT, UNIQUE KEY uk USING BTREE (a) COMMENT 'c')")->tables[0];
 *     $index = $table->constraints[0]->index;
 *     [$index->name, $index->method, $index->properties->comment] // => ['uk', 'btree', 'c']
 * @example Reading the covering columns and tablespace of a PostgreSQL key
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER, PRIMARY KEY (a) INCLUDE (b) USING INDEX TABLESPACE fast)')->tables[0];
 *     [$table->constraints[0]->index->include, $table->constraints[0]->index->properties->tablespace] // => [['b'], 'fast']
 * @example Rejecting a full-text key index
 *     new \SqlSemantics\Schema\Constraint\KeyIndex(properties: new \SqlSemantics\Schema\Index\Properties(\SqlSemantics\Schema\Index\Kind::FullText)); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class KeyIndex
{
    /**
     * @var list<string> PostgreSQL non-key columns stored in the index
     */
    public readonly array $include;

    /**
     * @param string|null $name MySQL index name; a primary key index is always named PRIMARY
     * @param string|null $method MySQL index access method (btree or hash)
     * @param list<string> $include PostgreSQL non-key columns stored in the index
     * @param Properties $properties Index options; an ordinary index whose NULL treatment belongs to the unique key
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $method = null,
        array $include = [],
        public readonly Properties $properties = new Properties(),
    ) {
        Collections::strings($include);
        $this->include = $include;
        if ($name === '' || $method === '' || in_array('', $include, true)) {
            throw new InvalidStructure('A key index uses nonempty names.');
        }
        if ($properties->kind !== Kind::Ordinary || !$properties->nullsDistinct || $properties->parser !== null) {
            throw new InvalidStructure('A key index is an ordinary index without a parser; NULL treatment belongs to the unique key.');
        }
    }

    /**
     * Rejects index options the key's dialect does not have: names, methods and MySQL options belong to MySQL, covering
     * columns, storage parameters and tablespaces to PostgreSQL.
     * @throws InvalidStructure
     */
    public function check(Dialect $dialect): void
    {
        $properties = $this->properties;
        $mySql = $this->name !== null || $this->method !== null || $properties->visible !== null || $properties->keyBlockSize !== null || $properties->comment !== null || $properties->engineAttribute !== null || $properties->secondaryEngineAttribute !== null;
        $postgreSql = $this->include !== [] || $properties->storageParameters !== [] || $properties->tablespace !== null;
        if (($mySql && $dialect !== Dialect::MySql) || ($postgreSql && $dialect !== Dialect::PostgreSql)) {
            throw new InvalidStructure('A key index option must belong to the key dialect.');
        }
    }
}
