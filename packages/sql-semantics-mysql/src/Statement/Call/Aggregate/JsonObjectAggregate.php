<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Aggregate;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Rules\Call\Windows;
use SqlSemantics\Platform\MySql\Rules\Query\Having\HavingScope;
use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A call of JSON_OBJECTAGG(key, value): a JSON object of the key and value pairs of a group.
 *
 * Rule: MYSQL-JSON-OBJECTAGG-001. Each operand takes its own ALL. The result
 * is JSON; it is NULL for a group without rows. A NULL key is the error
 * ER_JSON_DOCUMENT_NULL_KEY at run time. Terminates: the operands and the
 * window are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html#function_json-objectagg.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing JSON_OBJECTAGG()
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT JSON_OBJECTAGG('a', 1)");
 *     $query->field(0)->type->descriptor->name() // => 'JSON'
 */
final class JsonObjectAggregate implements SetFunction
{
    use Snapshot;

    /**
     * @param Scalar $key The key operand
     * @param Scalar $value The value operand
     * @param bool $keyAll Whether ALL is written before the key
     * @param bool $valueAll Whether ALL is written before the value
     * @param Name|WindowSpecification|null $over The window written after OVER
     */
    public function __construct(
        public readonly Scalar $key,
        public readonly Scalar $value,
        public readonly bool $keyAll = false,
        public readonly bool $valueAll = false,
        public readonly Name|WindowSpecification|null $over = null,
    ) {
    }

    /**
     * Tells whether the call aggregates its query block: it has no window.
     */
    public function aggregates(): bool
    {
        return $this->over === null;
    }

    /**
     * Derives the operands and the window; the result is JSON.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $environment = $this->aggregates() ? (new HavingScope())->leave($environment) : $environment;
        (new Arguments())->one($this->key, $derivation, $environment);
        (new Arguments())->one($this->value, $derivation, $environment);
        (new Windows())->derive($this->over, $derivation, $environment);

        return new ScalarFact(new Known(TypeClass::Json->descriptor()), Nullability::Nullable);
    }

    /**
     * Writes the call and its window.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON_OBJECTAGG')->glue()->symbol('(');
        if ($this->keyAll) {
            $out->keyword('ALL');
        }
        $out->node($this->key)->symbol(',');
        if ($this->valueAll) {
            $out->keyword('ALL');
        }
        $out->node($this->value)->symbol(')');
        (new Windows())->render($this->over, $out);
    }
}
