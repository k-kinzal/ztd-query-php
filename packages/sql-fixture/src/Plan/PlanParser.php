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
     * @throws PlanSyntaxException
     */
    public function parse(string $plan): FixturePlan
    {
        if (str_contains($plan, '<>')) {
            throw PlanSyntaxException::manyToManyUnsupported($plan);
        }

        $statements = (new Parsing\PlanStatements())->split($plan);
        if ($statements === []) {
            throw PlanSyntaxException::emptyPlan();
        }

        $parts = [];

        foreach ($statements as $statement) {
            $parsed = (new Parsing\RelationReader(new Parsing\RelationCursor($statement)))->parseStatement();
            if (is_string($parsed)) {
                $parts[] = $parsed;
                continue;
            }

            $parts = [...$parts, ...$parsed];
        }

        return new FixturePlan(...$parts);
    }
}
