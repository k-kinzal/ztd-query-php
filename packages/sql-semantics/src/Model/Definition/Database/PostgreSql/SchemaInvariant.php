<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database\PostgreSql;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates the elements created together with a PostgreSQL schema.
 * @visibility SqlSemantics
 */
final class SchemaInvariant
{
    /**
     * Requires PostgreSQL CREATE or GRANT elements and no elements when the schema may already exist.
     * @param list<BoundStatement> $elements Nested commands in request order
     * @throws InvalidStructure
     */
    public static function elements(Origin $origin, bool $ifNotExists, array $elements): void
    {
        Collections::objects($elements, BoundStatement::class);
        if ($origin->dialect !== Dialect::PostgreSql || ($ifNotExists && $elements !== [])) {
            throw new InvalidStructure('CREATE SCHEMA requires PostgreSQL, and IF NOT EXISTS cannot include schema elements.');
        }
        foreach ($elements as $element) {
            if (!in_array($element->kind, [StatementKind::Create, StatementKind::Grant], true) || $element->origin->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('A schema element creates a table, view, index, sequence or trigger, or grants privileges.');
            }
        }
    }
}
