<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonPair;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * `JSON_OBJECT (key : value, ... [NULL | ABSENT ON NULL] [WITH | WITHOUT UNIQUE] [RETURNING type])`.
 *
 * Mirrors PostgreSQL's `JsonObjectConstructor`. Without pairs, no NULL or
 * uniqueness clause can be written. Rule: PG-JSON-OBJECT-001. Facts: the
 * RETURNING type, otherwise `json`; never NULL. The result column is named
 * `json_object`.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE. Status: Implemented.
 *
 * @visibility public
 * @example Reading an empty object constructor
 *     $object = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonObjectConstructor([]);
 *     [$object->pairs, $object->outputName()->value] // => [[], 'json_object']
 * @example Rejecting a NULL clause without pairs
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonObjectConstructor([], \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling::Absent) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class JsonObjectConstructor implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var list<JsonPair> The key and value pairs
     */
    public readonly array $pairs;

    /**
     * @param list<JsonPair> $pairs The key and value pairs
     * @param JsonNullHandling|null $nulls The NULL clause written
     * @param JsonUniqueKeys|null $unique The uniqueness clause written
     * @param JsonReturning|null $returning The RETURNING clause
     */
    public function __construct(array $pairs, public readonly ?JsonNullHandling $nulls = null, public readonly ?JsonUniqueKeys $unique = null, public readonly ?JsonReturning $returning = null)
    {
        $this->pairs = Check::listOf($pairs, JsonPair::class, 'JSON_OBJECT takes key and value pairs.');
        Check::input($this->pairs !== [] || ($nulls === null && $unique === null), 'JSON_OBJECT without pairs takes no NULL or uniqueness clause.');
        $last = $this->pairs === [] ? null : $this->pairs[count($this->pairs) - 1]->value;
        Check::input($unique === null || $nulls !== null || $last === null || $last->format !== null || !(new Precedence())->takesUniqueness($last->value), 'A last value ending in IS JSON without a uniqueness clause would take the uniqueness clause; group it.');
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('json_object');
    }

    /**
     * Derives the pairs and the returning type.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        foreach ($this->pairs as $pair) {
            $pair->deriveClause($derivation, $environment);
        }
        $this->returning?->deriveClause($derivation, $environment);

        return new ScalarFact($this->returning?->type->typeFact($derivation->context) ?? new Known(Builtin::Json), Nullability::NotNull);
    }

    /**
     * Writes JSON_OBJECT with its pairs and clauses.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON_OBJECT')->glue()->symbol('(')->list($this->pairs);
        if ($this->nulls !== null) {
            $out->keyword($this->nulls->value, 'ON', 'NULL');
        }
        $out->node($this->unique)->node($this->returning)->symbol(')');
    }
}
