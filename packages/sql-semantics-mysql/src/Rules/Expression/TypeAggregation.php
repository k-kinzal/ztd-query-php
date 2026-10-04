<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Type\TypeDescriptor;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Derives the common type of values that share one result position: the branches of CASE, IF and COALESCE, the columns of a set operation and the rows of VALUES.
 *
 * Rule: MYSQL-TYPE-AGGREGATION-001, after `Item_aggregate_type` and
 * `Field::field_type_merge`. A bare NULL takes no part; with no other value
 * the result is the type of NULL. A missing or invalid type decides the
 * result (MYSQL-TYPE-ALTERNATIVES-001); the alternatives of a choice are
 * merged with each alternative of the other values. Two values of one
 * type merge to that type. Otherwise JSON merges with JSON only and
 * becomes LONGTEXT with anything else; two spatial types merge to GEOMETRY
 * and to LONGBLOB with anything else; a binary string with a non-binary
 * value gives VARBINARY, or the larger BLOB when a BLOB is involved; a
 * character string or an ENUM or SET with any other value gives VARCHAR, or
 * the larger TEXT when a TEXT is involved; temporal values merge to the
 * wider temporal type (DATE, TIME and TIMESTAMP with DATETIME give
 * DATETIME), YEAR merges with integers as an integer and other temporal
 * mixtures with numbers give VARCHAR; numbers merge by MYSQL-NUMERIC-MERGE-001.
 * Terminates: one pass over the values and their finitely many alternatives.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-functions.html#operator_case,
 * https://dev.mysql.com/doc/refman/8.4/en/union.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TypeAggregation
{
    /**
     * The order of the TEXT kinds by size.
     */
    private const TEXTS = [
        'TINYTEXT' => 1, 'TEXT' => 2, 'MEDIUMTEXT' => 3, 'LONG' => 3, 'LONG VARCHAR' => 3, 'LONG CHAR VARYING' => 3, 'LONGTEXT' => 4,
    ];

    /**
     * The order of the BLOB kinds by size.
     */
    private const BLOBS = ['TINYBLOB' => 1, 'BLOB' => 2, 'MEDIUMBLOB' => 3, 'LONG VARBINARY' => 3, 'LONGBLOB' => 4];

    /**
     * Answers the common type of values of the given types.
     *
     * @param list<TypeFact> $types
     */
    public function aggregate(array $types): TypeFact
    {
        $alternatives = new Alternatives();
        $blocking = $alternatives->blocking($types);
        if ($blocking !== null) {
            return $blocking;
        }
        $results = null;
        foreach ($types as $type) {
            $members = array_values(array_filter($alternatives->of($type), static fn (?TypeDescriptor $member): bool => $member !== null));
            if ($members === []) {
                continue;
            }
            $merged = [];
            foreach ($results ?? [null] as $result) {
                foreach ($members as $member) {
                    $merged[] = $result === null ? $member : $this->merge($result, $member);
                }
            }
            $results = $this->distinct($merged);
        }

        return $alternatives->known($results ?? []);
    }

    /**
     * Answers each type once, in first-seen order.
     *
     * @param list<TypeDescriptor> $types
     * @return list<TypeDescriptor>
     */
    public function distinct(array $types): array
    {
        $alternatives = new Alternatives();
        $distinct = [];
        foreach ($types as $type) {
            $distinct[$alternatives->key($type)] ??= $type;
        }

        return array_values($distinct);
    }

    /**
     * Answers the common type of two types.
     */
    public function merge(TypeDescriptor $left, TypeDescriptor $right): TypeDescriptor
    {
        if ((new Alternatives())->key($left) === (new Alternatives())->key($right)) {
            return $left;
        }
        if ($left instanceof Tuple || $right instanceof Tuple) {
            return $left;
        }
        $json = $this->json($left) || $this->json($right);
        if ($json || ($left instanceof Spatial xor $right instanceof Spatial)) {
            return $json ? new Character(CharacterKind::LongText) : new Binary(BinaryKind::LongBlob);
        }
        if ($left instanceof Spatial && $right instanceof Spatial) {
            return new Spatial(SpatialKind::Geometry);
        }
        if ($left instanceof Binary || $right instanceof Binary) {
            return $this->binary($left, $right);
        }
        if ($this->textual($left) || $this->textual($right)) {
            return $this->character($left, $right);
        }
        if ($left instanceof Temporal || $right instanceof Temporal) {
            return (new TemporalMerge())->merge($left, $right);
        }

        return (new NumericMerge())->merge($left, $right);
    }

    /**
     * Tells whether a type is JSON.
     */
    public function json(TypeDescriptor $type): bool
    {
        return $type instanceof Elementary && $type->kind === ElementaryKind::Json;
    }

    /**
     * Tells whether a type is a character string, ENUM or SET.
     */
    public function textual(TypeDescriptor $type): bool
    {
        return $type instanceof Character || $type instanceof Enumeration;
    }

    /**
     * Answers the binary string type two types merge to when one is a binary string.
     */
    public function binary(TypeDescriptor $left, TypeDescriptor $right): Binary
    {
        $size = max($left instanceof Binary ? self::BLOBS[$left->kind->value] ?? 0 : 0, $right instanceof Binary ? self::BLOBS[$right->kind->value] ?? 0 : 0);
        $size = max($size, $this->textSize($left), $this->textSize($right));

        return new Binary(match ($size) {
            0 => BinaryKind::VarBinary,
            1 => BinaryKind::TinyBlob,
            2 => BinaryKind::Blob,
            3 => BinaryKind::MediumBlob,
            default => BinaryKind::LongBlob,
        });
    }

    /**
     * Answers the character string type two types merge to when one is a character string.
     */
    public function character(TypeDescriptor $left, TypeDescriptor $right): Character
    {
        return new Character(match (max($this->textSize($left), $this->textSize($right))) {
            0 => CharacterKind::VarChar,
            1 => CharacterKind::TinyText,
            2 => CharacterKind::Text,
            3 => CharacterKind::MediumText,
            default => CharacterKind::LongText,
        });
    }

    /**
     * Answers the size rank of a TEXT type, and zero for every other type.
     */
    public function textSize(TypeDescriptor $type): int
    {
        return $type instanceof Character ? self::TEXTS[$type->kind->value] ?? 0 : 0;
    }
}
