<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A test of whether a value is JSON of a kind: `a IS [NOT] JSON [VALUE|ARRAY|OBJECT|SCALAR] [WITH|WITHOUT UNIQUE KEYS]`.
 *
 * Mirrors PostgreSQL's `JsonIsPredicate` node.
 *
 * Rule: PG-JSON-TEST-001. Facts: `boolean` for an operand of type `text`,
 * `json`, `jsonb` or `bytea` (a string constant is read as text), NULL when
 * the operand can be; any other operand type is reported. The operand must
 * keep its place before IS (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-MISC. Status: Implemented.
 *
 * @visibility public
 * @example Testing a string for a JSON object
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT '{}' IS NOT JSON OBJECT")->toString() // => "SELECT '{}' IS NOT JSON OBJECT"
 */
final class JsonTest implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested value
     * @param bool $negated Whether NOT is written
     * @param JsonItemKind $kind The kind of JSON item tested for
     * @param JsonUniqueKeys|null $uniqueness The key uniqueness clause, or null when none is written
     */
    public function __construct(
        public readonly Scalar $operand,
        public readonly bool $negated,
        public readonly JsonItemKind $kind,
        public readonly ?JsonUniqueKeys $uniqueness = null,
    ) {
        Check::input((new Precedence())->before($operand, Precedence::IS), 'The tested value needs parentheses to keep its place.');
    }

    /**
     * Derives the operand and checks that it can hold JSON text.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $checks = new OperandChecks();

        return new ScalarFact($checks->accepted($derivation, $operand->type, [Builtin::Text, Builtin::Varchar, Builtin::Bpchar, Builtin::Json, Builtin::Jsonb, Builtin::Bytea], 'IS JSON'), $checks->nullability([$operand]));
    }

    /**
     * Writes the value and the test.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('IS');
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword(...explode(' ', $this->kind->value))->node($this->uniqueness);
    }
}
