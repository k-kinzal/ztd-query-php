<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Platform\MySql\Rules\Typing\Text\ResultBounds;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * Resolves the results of flow control functions: IF, IFNULL and COALESCE settle the types of their branches, NULLIF keeps the type of its first argument.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class ControlResults
{
    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        return [
            'IF' => static fn (Invocation $call): ?Domain => $call->aggregation()->of((new self())->branches($call, [1, 2]), 'if', $call->derivation),
            'IFNULL' => static fn (Invocation $call): ?Domain => $call->aggregation()->of((new self())->branches($call, array_keys($call->domains)), 'ifnull', $call->derivation),
            'COALESCE' => static fn (Invocation $call): ?Domain => $call->aggregation()->of((new self())->branches($call, array_keys($call->domains)), 'coalesce', $call->derivation),
            'NULLIF' => $this->nullable(...),
        ];
    }

    /**
     * Keeps the first argument's evaluation kind while resolving its returned field.
     * Temporal results report strings; legacy numeric results use LONGLONG or DOUBLE.
     */
    public function nullable(Invocation $call): Domain
    {
        $node = $call->nodes[0] ?? null;
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }
        $column = $node !== null && $call->derivation->facts()->scalar($node)->resolution instanceof ResolvedColumn;
        $legacy = (new FormatResults())->legacy($call);
        $domain = $call->domain(0);
        if ($legacy && $domain->kind === Kind::Null) {
            return new Domain(Kind::String, Field::VarString, 0, 0, false, $domain->collation, [], $domain->coercibility);
        }
        if ($legacy && in_array($domain->kind, [Kind::Integer, Kind::Year, Kind::Bit, Kind::Double], true)) {
            return new Domain($domain->kind, $domain->kind === Kind::Double ? Field::Double : Field::LongLong, $domain->length, $domain->decimals, $domain->unsigned, null, [], Coercibility::Numeric, storageField: $domain->kind === Kind::Double ? null : $this->integerStorage($domain));
        }
        if ($domain->kind === Kind::Year && $call->derivation->context->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql8044) {
            return new Domain(Kind::Year, Field::VarString, $domain->length, 0, true, null, [], Coercibility::Numeric, displayDecimals: Domain::NOT_FIXED, storageField: Field::Long);
        }
        if ($domain->kind->temporal() || $domain->kind === Kind::Year) {
            return new Domain($domain->kind, Field::VarString, $domain->length, $domain->decimals, false, Collation::known('latin1_swedish_ci'), [], Coercibility::Numeric, displayDecimals: $legacy ? null : Domain::NOT_FIXED);
        }

        $result = (new ResultBounds())->selected($domain, $legacy, $column);

        return in_array($domain->kind, [Kind::Integer, Kind::Bit], true) ? $result->withStorageField($this->integerStorage($domain)) : $result;
    }

    /**
     * Resolves the storage family of an integer NULLIF result. Small integer
     * and YEAR inputs store as INT, while BIGINT and BIT use width-based integer
     * storage. Temporary derived columns independently narrow by result width.
     */
    public function integerStorage(Domain $domain): Field
    {
        return $domain->storageField ?? ($domain->kind === Kind::Year || ($domain->kind === Kind::Integer && !in_array($domain->field, [Field::LongLong, Field::Bit], true)) ? Field::Long : Field::LongLong);
    }

    /**
     * Infers absent user-variable operands from the other result branches.
     *
     * Explicitly initialized variables retain their recorded type. Each absent
     * occurrence is inferred independently from the first other resolved branch,
     * including a literal NULL; this does not create a session entry. The remaining
     * branches take part only in the final aggregation of result types.
     * Verified through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
     *
     * @param list<int> $positions The result-argument positions
     * @return list<Domain>
     */
    public function branches(Invocation $call, array $positions): array
    {
        $domains = array_map($call->domain(...), $positions);
        $nodes = array_map(static fn (int $position): ?\SqlSemantics\Statement\Scalar => $call->nodes[$position] ?? null, $positions);

        return (new \SqlSemantics\Platform\MySql\Rules\Typing\BranchInference())->resolve($domains, $nodes, $call->derivation);
    }
}
