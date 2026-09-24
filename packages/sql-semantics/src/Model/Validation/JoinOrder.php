<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\Relation\Joining\CrossJoin;
use SqlSemantics\Model\Relation\Joining\OnJoin;
use SqlSemantics\Model\Relation\Joining\UsingJoin;
use SqlSemantics\Model\TableUse;

/**
 * Validates that a join whose input order is fixed, MySQL's STRAIGHT_JOIN, belongs to a MySQL statement.
 * @visibility SqlSemantics
 */
final class JoinOrder
{
    /**
     * Walks every join of a relation input.
     * @throws InvalidStructure
     */
    public static function straight(TableUse|Join|null $input, Dialect $dialect): void
    {
        if (!$input instanceof Join) {
            return;
        }
        if (($input instanceof OnJoin || $input instanceof UsingJoin || $input instanceof CrossJoin) && $input->straight && $dialect !== Dialect::MySql) {
            throw new InvalidStructure('STRAIGHT_JOIN requires MySQL.');
        }
        self::straight($input->left, $dialect);
        self::straight($input->right, $dialect);
    }
}
