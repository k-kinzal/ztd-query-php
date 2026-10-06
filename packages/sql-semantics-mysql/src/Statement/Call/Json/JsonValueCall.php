<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Json;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A call of JSON_VALUE(document, path [RETURNING type] [ON EMPTY] [ON ERROR]) of MySQL 8.0.21 and later.
 *
 * Rule: MYSQL-JSON-VALUE-001. The value at the path is returned in the
 * RETURNING type, by default VARCHAR(512) in utf8mb4; it is NULL when the
 * document is NULL, when the path finds nothing and by default on an error.
 * The document is a primary expression. Terminates: the parts are strict
 * parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html#function_json-value.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing JSON_VALUE() with RETURNING
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT JSON_VALUE('{\"a\": 1}', '$.a' RETURNING SIGNED NULL ON EMPTY)");
 *     $query->field(0)->type->descriptor->name() // => 'SIGNED'
 */
final class JsonValueCall implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $document The JSON document; a primary expression
     * @param StringLiteral $path The path
     * @param CastTarget|null $returning The written RETURNING type
     * @param JsonResponse|null $onEmpty The ON EMPTY response
     * @param JsonResponse|null $onError The ON ERROR response
     */
    public function __construct(
        public readonly Scalar $document,
        public readonly StringLiteral $path,
        public readonly ?CastTarget $returning = null,
        public readonly ?JsonResponse $onEmpty = null,
        public readonly ?JsonResponse $onError = null,
    ) {
        Check::input((new Precedence())->admits($document, Precedence::SIMPLE_EXPR), 'The document of JSON_VALUE needs a grouping.');
    }

    /**
     * Derives the parts; the result has the RETURNING type.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        (new Arguments())->one($this->document, $derivation, $environment);
        (new Arguments())->one($this->path, $derivation, $environment);
        foreach ([$this->onEmpty?->default, $this->onError?->default] as $default) {
            if ($default !== null) {
                (new Arguments())->one($default, $derivation, $environment);
            }
        }

        return new ScalarFact(new Known($this->returning ?? TypeClass::Character->descriptor()), Nullability::Nullable);
    }

    /**
     * Writes the call.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON_VALUE')->glue()->symbol('(')->node($this->document)->symbol(',')->node($this->path);
        if ($this->returning !== null) {
            $out->keyword('RETURNING')->node($this->returning);
        }
        if ($this->onEmpty !== null) {
            $out->node($this->onEmpty)->keyword('ON', 'EMPTY');
        }
        if ($this->onError !== null) {
            $out->node($this->onError)->keyword('ON', 'ERROR');
        }
        $out->symbol(')');
    }
}
