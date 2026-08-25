<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

/**
 * Reads the relation syntax of DBML into a plan.
 *
 *     order.id < order_detail.order_id
 *     order_detail.order_id > order.id
 *     order.id - order_shipping.order_id
 *     order.(shop_id, no) < order_detail.(shop_id, order_no)
 *     order.id < [order_detail.order_id, shipment.order_id]
 *     order_detail.order_id >? order.id
 *
 * Relations are separated by a comma or a newline. A plan naming a single
 * table and no relation is just that table name, so every table name is
 * already a valid plan.
 */
final class PlanParser
{
    /**
     * @param PlanStatements $statements Separates a plan into the statements written in it
     * @param PlanStatementReader $reader Reads one statement
     */
    public function __construct(
        private readonly PlanStatements $statements = new PlanStatements(),
        private readonly PlanStatementReader $reader = new PlanStatementReader(),
    ) {
    }

    /**
     * Reads a plan.
     *
     * @param string $plan Plan as it was written
     *
     * @return FixturePlan The tables and relations it declares
     *
     * @throws PlanSyntaxException When the plan is empty, declares a many-to-many, or is not written as relations
     */
    public function parse(string $plan): FixturePlan
    {
        if (str_contains($plan, '<>')) {
            throw PlanSyntaxException::manyToManyUnsupported($plan);
        }

        $statements = $this->statements->of($plan);
        if ($statements === []) {
            throw PlanSyntaxException::emptyPlan();
        }

        $parts = [];
        foreach ($statements as $statement) {
            $read = $this->reader->read(new PlanCursor($statement));
            if (is_string($read)) {
                $parts[] = $read;
                continue;
            }
            $parts = [...$parts, ...$read];
        }

        return new FixturePlan(...$parts);
    }
}
