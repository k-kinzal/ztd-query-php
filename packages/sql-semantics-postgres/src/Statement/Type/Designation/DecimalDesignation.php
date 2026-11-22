<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\TypeFact;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\Modifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;

/**
 * The type spelled `NUMERIC`, `DECIMAL` or `DEC`, with optional precision and scale.
 *
 * Rule: PG-TYPE-DECIMAL-001. The grammar reads the modifiers as expressions;
 * the type takes one or two integer constants. Facts: PG-TYPE-MODIFIER-001.
 * Source: https://www.postgresql.org/docs/17/datatype-numeric.html#DATATYPE-NUMERIC-DECIMAL. Status: Implemented.
 *
 * @visibility SqlSemantics
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
