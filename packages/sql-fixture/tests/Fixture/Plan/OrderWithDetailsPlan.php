<?php

declare(strict_types=1);

namespace Tests\Fixture\Plan;

use SqlFixture\Plan\FixturePlan;

/**
 * A named plan, defined by subclassing.
 */
final class OrderWithDetailsPlan extends FixturePlan
{
    protected static function definition(): string
    {
        return 'order.id < order_detail.order_id, order.customer_id > customer.id';
    }
}
