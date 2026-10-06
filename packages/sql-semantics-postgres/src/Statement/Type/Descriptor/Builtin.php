<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor;

use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * A data type every PostgreSQL database has in `pg_catalog`, identified by its catalog name.
 *
 * The cases are the general-purpose types, the object-identifier types, the
 * range types and the pseudo-types of the manual. The name reported is the
 * one the server displays, which is the SQL spelling where the standard has
 * one: `int4` is `integer`, `bpchar` is `character`.
 * Source: https://www.postgresql.org/docs/17/datatype.html#DATATYPE-TABLE,
 * https://www.postgresql.org/docs/17/datatype-oid.html, https://www.postgresql.org/docs/17/datatype-pseudo.html.
 *
 * @visibility public
 * @example Reading the type of an integer constant
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1');
 *     [$query->field(0)->type->descriptor, $query->field(0)->type->descriptor->name()] // => [\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, 'integer']
 */
enum Builtin: string implements TypeDescriptor
{
    case Bool = 'bool';
    case Bytea = 'bytea';
    case Char = 'char';
    case Name = 'name';
    case Int8 = 'int8';
    case Int2 = 'int2';
    case Int4 = 'int4';
    case Text = 'text';
    case Oid = 'oid';
    case Tid = 'tid';
    case Xid = 'xid';
    case Cid = 'cid';
    case Xid8 = 'xid8';
    case Json = 'json';
    case Jsonb = 'jsonb';
    case Jsonpath = 'jsonpath';
    case Xml = 'xml';
    case Float4 = 'float4';
    case Float8 = 'float8';
    case Money = 'money';
    case Bpchar = 'bpchar';
    case Varchar = 'varchar';
    case Date = 'date';
    case Time = 'time';
    case Timetz = 'timetz';
    case Timestamp = 'timestamp';
    case Timestamptz = 'timestamptz';
    case Interval = 'interval';
    case Bit = 'bit';
    case Varbit = 'varbit';
    case Numeric = 'numeric';
    case Uuid = 'uuid';
    case Point = 'point';
    case Line = 'line';
    case Lseg = 'lseg';
    case Box = 'box';
    case Path = 'path';
    case Polygon = 'polygon';
    case Circle = 'circle';
    case Cidr = 'cidr';
    case Inet = 'inet';
    case Macaddr = 'macaddr';
    case Macaddr8 = 'macaddr8';
    case Tsvector = 'tsvector';
    case Tsquery = 'tsquery';
    case PgLsn = 'pg_lsn';
    case PgSnapshot = 'pg_snapshot';
    case TxidSnapshot = 'txid_snapshot';
    case Refcursor = 'refcursor';
    case Regclass = 'regclass';
    case Regcollation = 'regcollation';
    case Regconfig = 'regconfig';
    case Regdictionary = 'regdictionary';
    case Regnamespace = 'regnamespace';
    case Regoper = 'regoper';
    case Regoperator = 'regoperator';
    case Regproc = 'regproc';
    case Regprocedure = 'regprocedure';
    case Regrole = 'regrole';
    case Regtype = 'regtype';
    case Int4range = 'int4range';
    case Int8range = 'int8range';
    case Numrange = 'numrange';
    case Tsrange = 'tsrange';
    case Tstzrange = 'tstzrange';
    case Daterange = 'daterange';
    case Int4multirange = 'int4multirange';
    case Int8multirange = 'int8multirange';
    case Nummultirange = 'nummultirange';
    case Tsmultirange = 'tsmultirange';
    case Tstzmultirange = 'tstzmultirange';
    case Datemultirange = 'datemultirange';
    case Record = 'record';
    case Void = 'void';
    case Cstring = 'cstring';
    case Unknown = 'unknown';

    /**
     * Names the type as the server displays it.
     */
    public function name(): string
    {
        return [
            'bool' => 'boolean', 'int2' => 'smallint', 'int4' => 'integer', 'int8' => 'bigint', 'float4' => 'real',
            'float8' => 'double precision', 'bpchar' => 'character', 'varchar' => 'character varying', 'char' => '"char"',
            'time' => 'time without time zone', 'timetz' => 'time with time zone', 'timestamp' => 'timestamp without time zone',
            'timestamptz' => 'timestamp with time zone', 'varbit' => 'bit varying',
        ][$this->value] ?? $this->value;
    }
}
