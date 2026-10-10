<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Result;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\SetFacts;
use SqlSemantics\Platform\MySql\Rules\Typing\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\Collations;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field as ResultField;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;

/**
 * Resolves the externally returned fields of a set statement separately from its
 * materialized query fields. MySQL 5.7 streams an unordered UNION ALL and reports
 * strings as VARCHAR with unfixed decimals. A scalar or derived-table consumer
 * instead reads the temporary column. Original operand facts retain the bounds
 * before storage allocation and byte conversion. Verified with Testcontainers
 * MySQL 5.7 through GROUP_CONCAT, nested unions and scalar consumers.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SetOutput
{
    /**
     * Derives a query once and resolves its statement result from those facts.
     */
    public function derive(Query $query, Derivation $derivation): QueryFact
    {
        $fact = $derivation->query($query, $derivation->environment());
        $body = $this->body($query);
        if ($derivation->context->profile->grammar !== GrammarRelease::MySql5744 || !$this->streamed($body) || !$fact->shape->complete()) {
            return $fact;
        }
        $fields = [];
        foreach ($fact->projection as $field) {
            $domain = $field instanceof Field ? (new Precision())->domain($field->type) : null;
            if (!$field instanceof Field || $domain?->kind !== Kind::String) {
                $fields[] = $field;
                continue;
            }
            $operands = $this->domains($body, $field->position, $derivation);
            $settled = $operands === null ? null : (new Aggregation(new Collations(Settings::of($derivation->context)->connection)))->of($operands, 'UNION', $derivation);
            $returned = $this->returned($domain, $settled);
            if ($returned === null) {
                $fields[] = $field;
                continue;
            }
            $type = new Known($returned);
            $fields[] = new Field($field->position, new OutputSlot($field->name, $type, $field->nullability, unnamed: $field->slot->unnamed));
        }

        return $fields === $fact->projection ? $fact : new QueryFact($fields, $fact->names, $fact->aggregates);
    }

    /**
     * Retains fractional precision for textually reported temporal operands in a
     * streamed MySQL 5.7 UNION ALL. Stored set columns instead clear decimals.
     * Verified through NULLIF over TIME, DATETIME and TIMESTAMP columns on 5.7.
     */
    public function returned(Domain $stored, ?Domain $settled): ?Domain
    {
        if ($settled?->kind->temporal() === true && $settled->field === ResultField::VarString) {
            return new Domain(Kind::String, ResultField::VarString, $stored->length, $settled->decimals, false, $stored->collation, [], $stored->coercibility);
        }

        return $settled?->kind === Kind::String ? Domain::string($settled->length, $settled->collation, ResultField::VarString, $settled->coercibility) : null;
    }

    /**
     * Unwraps statement parentheses and a global LIMIT, retaining global ordering.
     */
    public function body(Query $query): Query
    {
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryStatement || ($query instanceof QueryExpression && $query->orderBy === [])) {
            $query = $query instanceof QueryExpression ? $query->body : $query->query;
        }

        return $query;
    }

    /**
     * Tells whether a set chain contains only UNION ALL without global ordering.
     */
    public function streamed(Query $query): bool
    {
        if (!$query instanceof SetOperation) {
            return false;
        }
        while ($query instanceof SetOperation) {
            if ($query->operator !== SetOperator::Union || $query->quantifier !== SetQuantifier::All || !$query->left instanceof Query) {
                return false;
            }
            $query = $query->left;
        }

        return true;
    }

    /**
     * Reads the original operand domains without resolving their expressions again.
     *
     * @return list<Domain>|null
     */
    public function domains(Query $query, int $position, Derivation $derivation): ?array
    {
        if ($query instanceof SetOperation && $query->left instanceof Query) {
            $left = $this->domains($query->left, $position, $derivation);
            $right = $this->domains($query->right, $position, $derivation);

            return $left === null || $right === null ? null : [...$left, ...$right];
        }
        $slot = $derivation->facts()->query($query)->shape->slots[$position] ?? null;
        $domain = $slot === null ? null : (new ResultSlots())->operand($slot->type, (new SetFacts())->combined($query));

        return $domain === null ? null : [$domain];
    }
}
