<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\Modifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * The type spelled `NUMERIC`, `DECIMAL` or `DEC`, with optional precision and scale.
 *
 * Rule: PG-TYPE-DECIMAL-001. The grammar reads the modifiers as expressions;
 * the type takes one or two integer constants. Facts: PG-TYPE-MODIFIER-001.
 * Source: https://www.postgresql.org/docs/17/datatype-numeric.html#DATATYPE-NUMERIC-DECIMAL. Status: Implemented.
 *
 * @visibility public
 * @example Reading precision and scale
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT decimal(10, 2) '1.5'");
 *     $query->field(0)->type->descriptor->modifiers // => [10, 2]
 */
final class DecimalDesignation implements TypeDesignation
{
    use Snapshot;

    /**
     * @var list<Scalar> The modifier expressions in order
     */
    public readonly array $modifiers;

    /**
     * @param DecimalKeyword $keyword The spelling
     * @param list<Scalar> $modifiers The modifier expressions in order
     */
    public function __construct(public readonly DecimalKeyword $keyword, array $modifiers = [])
    {
        $this->modifiers = Check::listOf($modifiers, Scalar::class, 'Type modifiers are expressions.');
    }

    /**
     * Answers the numeric type with its modifiers.
     */
    public function typeFact(AnalysisContext $context, bool $constant): TypeFact
    {
        return (new Modifiers())->apply(Builtin::Numeric, $this->modifiers);
    }

    /**
     * Answers the catalog name of the type.
     */
    public function catalogName(): Name
    {
        return new Name(Builtin::Numeric->value);
    }

    /**
     * Derives the modifier expressions.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->modifiers as $modifier) {
            $derivation->scalar($modifier, $environment);
        }
    }

    /**
     * Writes the keyword and the modifiers.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->keyword->value);
        if ($this->modifiers !== []) {
            $out->symbol('(')->list($this->modifiers)->symbol(')');
        }
    }
}
