<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * The type spelled `FLOAT` or `FLOAT(p)`.
 *
 * Rule: PG-TYPE-FLOAT-001. A precision of 1 to 24 binary digits selects
 * `real`, 25 to 53 selects `double precision`, and no precision is `double
 * precision`. The grammar rejects any other precision. Facts: `Known`.
 * Source: https://www.postgresql.org/docs/17/datatype-numeric.html#DATATYPE-FLOAT. Status: Implemented.
 *
 * @visibility public
 * @example Reading the type a float precision selects
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT float(24) '1'");
 *     $query->field(0)->type->descriptor->name() // => 'real'
 * @example Rejecting a precision the grammar does not accept
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\FloatDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('54')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class FloatDesignation implements TypeDesignation
{
    use Snapshot;

    /**
     * @param IntegerConstant|null $precision The precision in binary digits, 1 to 53
     */
    public function __construct(public readonly ?IntegerConstant $precision = null)
    {
        Check::input($precision === null || (strlen($precision->digits) <= 2 && (int) $precision->digits >= 1 && (int) $precision->digits <= 53), 'A float precision is 1 to 53 binary digits.');
    }

    /**
     * Answers the catalog type the precision selects.
     */
    public function builtin(): Builtin
    {
        return $this->precision !== null && (int) $this->precision->digits <= 24 ? Builtin::Float4 : Builtin::Float8;
    }

    /**
     * Answers the catalog type of the spelling.
     */
    public function typeFact(AnalysisContext $context, bool $constant): TypeFact
    {
        return new Known($this->builtin());
    }

    /**
     * Answers the catalog name of the type.
     */
    public function catalogName(): Name
    {
        return new Name($this->builtin()->value);
    }

    /**
     * Derives nothing: the precision is a constant.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keyword and the precision.
     */
    public function render(Output $out): void
    {
        $out->keyword('FLOAT');
        if ($this->precision !== null) {
            $out->symbol('(')->node($this->precision)->symbol(')');
        }
    }
}
