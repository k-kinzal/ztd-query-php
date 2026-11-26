<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\TypeFact;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\Modifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;

/**
 * A character type spelled with keywords, with an optional length.
 *
 * Rule: PG-TYPE-CHARACTER-001. Without VARYING the type is `character`,
 * which is one character long when no length is written and unconstrained
 * when it types a constant; with VARYING, or spelled VARCHAR, it is
 * `character varying`, unlimited without a length. A length is 1 to 10485760.
 * Source: https://www.postgresql.org/docs/17/datatype-character.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Rejecting VARYING after VARCHAR
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterDesignation(\SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterKeyword::Varchar, true) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class CharacterDesignation implements TypeDesignation
{
    use Snapshot;

    /**
     * @param CharacterKeyword $keyword The spelling
     * @param bool $varying Whether VARYING is written
     * @param IntegerConstant|null $length The length in characters
     */
    public function __construct(public readonly CharacterKeyword $keyword, public readonly bool $varying = false, public readonly ?IntegerConstant $length = null)
    {
        Check::input(!$varying || $keyword !== CharacterKeyword::Varchar, 'VARCHAR is not followed by VARYING.');
    }

    /**
     * Answers the catalog type the spelling denotes.
     */
    public function builtin(): Builtin
    {
        return $this->varying || $this->keyword === CharacterKeyword::Varchar ? Builtin::Varchar : Builtin::Bpchar;
    }

    /**
     * Answers the character type with its length.
     */
    public function typeFact(AnalysisContext $context, bool $constant): TypeFact
    {
        if ($this->length === null) {
            return new Known($this->builtin() === Builtin::Bpchar && !$constant ? new Parameterized(Builtin::Bpchar, 1) : $this->builtin());
        }

        return (new Modifiers())->constrained($this->builtin(), [$this->length->digits]);
    }

    /**
     * Answers the catalog name of the type.
     */
    public function catalogName(): Name
    {
        return new Name($this->builtin()->value);
    }

    /**
     * Derives nothing: the length is a constant.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keywords and the length.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->keyword->value));
        if ($this->varying) {
            $out->keyword('VARYING');
        }
        if ($this->length !== null) {
            $out->symbol('(')->node($this->length)->symbol(')');
        }
    }
}
