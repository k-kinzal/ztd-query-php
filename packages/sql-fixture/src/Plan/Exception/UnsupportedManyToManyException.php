<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Exception;

use SqlFixture\Plan\PlanSyntaxException;

/**
 * A many-to-many relation omits its junction table.
 */
final class UnsupportedManyToManyException extends PlanSyntaxException
{
    /**
     * A many-to-many relation omits its junction table.
     */
    public function __construct(
        public readonly string $plan,
    ) {
        parent::__construct(sprintf(
            'The <> operator is not supported, because a fixture has to put rows in the '
            . 'junction table and so must name it. Write the two halves instead, for '
            . 'example "order.id < order_detail.order_id, order_detail.product_id > '
            . 'product.id". Plan: %s',
            $plan
        ));
    }
}
