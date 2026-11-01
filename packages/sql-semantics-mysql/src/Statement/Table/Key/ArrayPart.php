<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastProblems;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Scalar;

/**
 * A multi-valued index part: one JSON document expression and its element type.
 *
 * This value is an index part, not a scalar expression. Each converted array
 * element contributes an index entry for the same stored row. The operand is
 * resolved against the indexed table, while the element target belongs to the
 * key definition. Written ordering is retained for definition diagnostics,
 * including declarations whose IF NOT EXISTS branch leaves a table intact.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html#create-index-multi-valued.
 *
 * @visibility public
 * @example Constructing an array key without a scalar array cast
 *     $part = new \SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Signed));
 *     $part instanceof \SqlSemantics\Statement\Scalar // => false
 * @example Refusing a JSON element target
 *     new \SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Json)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an impossible temporal precision
 *     new \SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Time, '7')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ArrayPart implements KeyPart, Operand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar $expression The JSON document whose elements supply the key entries
     * @param CastTarget $target The SQL type of each indexed element
     * @param Direction|null $direction The written ordering, which a created multi-valued index refuses
     */
    public function __construct(public readonly Scalar $expression, public readonly CastTarget $target, public readonly ?Direction $direction = null, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input((new CastProblems())->target($target, true) === null, 'A multi-valued index needs a supported element type and precision.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Resolves the JSON document expression against the indexed table.
     */
    public function deriveKeyPart(Derivation $derivation, Environment $scope): void
    {
        $derivation->scalar($this, $scope);
    }

    /**
     * Resolves the type of each index entry separately from the JSON document.
     *
     * This is an operand fact for a key entry, not a scalar expression over
     * the document. A NULL document contributes a nullable index entry.
     */
    public function deriveScalar(Derivation $derivation, Environment $scope): ScalarFact
    {
        $fact = (new Operands())->single($derivation->scalar($this->expression, $scope), $derivation);
        $release = $derivation->context->profile->grammar;
        $settings = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($derivation->context);
        $operand = (new \SqlSemantics\Platform\MySql\Rules\Typing\Precision())->domain($fact->type);
        $domain = (new \SqlSemantics\Platform\MySql\Rules\Typing\Casts($settings, $release))->array($operand, $this->target);

        return new ScalarFact(new \SqlSemantics\Statement\Type\Known($domain), $fact->nullability);
    }

    /**
     * Writes the parenthesized array conversion in its index role.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->keyword('CAST')->glue()->symbol('(')->node($this->expression)->keyword('AS')->node($this->target)->keyword('ARRAY')->symbol(')')->symbol(')');
        if ($this->direction !== null) {
            $out->keyword($this->direction->value);
        }
    }
}
