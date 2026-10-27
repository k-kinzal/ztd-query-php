<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Json;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Typing\Casts;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
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
 * @example Refusing an impossible spatial result before derivation
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['$']), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Point)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a return precision outside its type
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['$']), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Decimal, '66', '0')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a return width outside its type
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['$']), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Char, '4294967296')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Typing JSON_VALUE() with RETURNING
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT JSON_VALUE('{\"a\": 1}', '$.a' RETURNING SIGNED NULL ON EMPTY)");
 *     $query->field(0)->type->descriptor->name() // => 'BIGINT'
 */
final class JsonValueCall implements Scalar
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * The constant path's selection steps or decoded-operand condition.
     */
    public readonly Path|Path\Invalid $pathValue;

    /**
     * @param Scalar $document The JSON document; a primary expression
     * @param StringLiteral $path The path
     * @param CastTarget|null $returning The written RETURNING type
     * @param JsonResponse|null $onEmpty The ON EMPTY response
     * @param JsonResponse|null $onError The ON ERROR response
     * @param Path|Path\Invalid|null $pathValue Explicit selection steps, when constructed from semantic values; they must agree with the literal operand
     */
    public function __construct(
        public readonly Scalar $document,
        public readonly StringLiteral $path,
        public readonly ?CastTarget $returning = null,
        public readonly ?JsonResponse $onEmpty = null,
        public readonly ?JsonResponse $onError = null,
        ?ScalarFact $scalarInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
        Path|Path\Invalid|null $pathValue = null,
    ) {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input((new Precedence())->admits($document, Precedence::SIMPLE_EXPR), 'The document of JSON_VALUE needs a grouping.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastProblems())->returning($returning) === null, 'JSON_VALUE needs a nonspatial return type with valid width, precision and scale.');
        $interpreted = Path::read($path->value());
        Check::input($pathValue === null || (new \SqlSemantics\Validation\Equivalence())->difference($pathValue, $interpreted) === null, 'JSON path steps must agree with the decoded path literal.');
        $this->pathValue = $pathValue ?? $interpreted;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the parts; the result has the RETURNING type, resolved as {@see Casts::returning()} says.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $document = (new Arguments())->one($this->document, $derivation, $environment);
        $type = (new \SqlSemantics\Platform\MySql\Rules\Typing\Precision())->domain($document->type);
        if ($document->resolution instanceof \SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding || ($document->resolution instanceof \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableBinding && !$document->resolution->exists) || ($type !== null && ($type->field === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Geometry || !in_array($type->kind, [\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Json, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Null], true)))) {
            $derivation->report(new \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonDocument($this));
        } elseif ($this->pathValue instanceof Path\Invalid) {
            $derivation->report(new \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonPath($this));
        }
        (new Arguments())->one($this->path, $derivation, $environment);
        foreach ([$this->onEmpty?->default, $this->onError?->default] as $default) {
            if ($default !== null) {
                (new Arguments())->one($default, $derivation, $environment);
            }
        }

        if ($this->returning !== null) {
            (new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastNotices())->derive($this->returning, $derivation);
        }
        $domain = (new Casts(Settings::of($derivation->context), $derivation->context->profile->grammar))->returning($this->returning);

        return new ScalarFact(new Known($domain), Nullability::Nullable);
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
