<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

/**
 * Which end of a written relation is meant, before roles are worked out.
 *
 * `order.id < order_detail.order_id` and `order_detail.order_id > order.id`
 * describe the same relation with the sides swapped, so parent and child are
 * derived from the operator rather than from the order they were written in.
 */
enum RelationSide
{
    case Left;
    case Right;

    public function opposite(): self
    {
        return $this === self::Left ? self::Right : self::Left;
    }
}
