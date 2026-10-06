<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A key and its value in JSON_OBJECT or JSON_OBJECTAGG.
 *
 * Mirrors PostgreSQL's `JsonPair` node. With the VALUE spelling the key
 * is a primary expression; a key that would need parentheses is rejected.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE.
 *
 * @visibility public
 * @example Reading a key and value pair
 *     $pair = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonPair(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()));
 *     $pair->spelling->value // => ':'
 */
final class JsonPair implements Clause
{
    use Snapshot;

    /**
     * @param Scalar $key The key
     * @param JsonValueExpression $value The value
     * @param KeyValueSpelling $spelling How the key joins the value
     */
    public function __construct(public readonly Scalar $key, public readonly JsonValueExpression $value, public readonly KeyValueSpelling $spelling = KeyValueSpelling::Colon)
    {
        Check::input($spelling === KeyValueSpelling::Colon || (new Precedence())->primary($key), 'A key before VALUE is a primary expression; group it.');
    }

    /**
     * Derives the key and the value.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->key, $environment);
        $this->value->deriveValue($derivation, $environment);
    }

    /**
     * Writes the key, the joining word or symbol, and the value.
     */
    public function render(Output $out): void
    {
        $out->node($this->key);
        if ($this->spelling === KeyValueSpelling::Value) {
            $out->keyword('VALUE');
        } else {
            $out->symbol(':');
        }
        $out->node($this->value);
    }
}
