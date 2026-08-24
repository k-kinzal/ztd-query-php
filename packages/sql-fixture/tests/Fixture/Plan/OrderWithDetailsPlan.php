<?php

declare(strict_types=1);

namespace Tests\Fixture\Plan;

use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Relation;

/**
 * A plan declared as a type rather than written out as a string.
 */
final class OrderWithDetailsPlan extends FixturePlan
{
    public function __construct()
    {
        parent::__construct(
            Relation::oneToMany('order.id', 'order_detail.order_id'),
            Relation::manyToOne('order.customer_id', 'customer.id'),
        );
    }
}
