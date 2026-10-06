<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\AttributeReader;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * An attribute value read as a Boolean, such as `hashes` of an operator.
 *
 * `defGetBoolean` reads an attribute given alone as true, the integers 0
 * and 1, and the texts `true`, `false`, `on` and `off` without regard to case,
 * whether written as a word, a keyword or a string.
 * Source: https://www.postgresql.org/docs/17/sql-createoperator.html, `defGetBoolean` in `src/backend/commands/define.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading an attribute given alone
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE OPERATOR === (rightarg = int4, function = f, hashes, merges = off)');
 *     [$operation->statement->definition[2]->value->value(), $operation->statement->definition[3]->value->value()] // => [true, false]
 * @example Rejecting a value that is not a Boolean
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\BooleanArgument(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('yes')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class BooleanArgument implements AttributeArgument
{
    use Snapshot;

    /**
     * @param TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null $written The value as written; null when the attribute is given alone
     */
    public function __construct(public readonly TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null $written = null)
    {
        Check::input((new AttributeReader())->boolean($written) !== null, 'A Boolean attribute is given alone, as 0 or 1, or as true, false, on or off.');
    }

    /**
     * Answers the Boolean the server reads.
     */
    public function value(): bool
    {
        return (new AttributeReader())->boolean($this->written) ?? true;
    }

    /**
     * Tells whether the reading reads a Boolean.
     */
    public function fits(Reading $reading): bool
    {
        return $reading === Reading::Boolean;
    }

    /**
     * Derives the modifier expressions of a value written as a type name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->written?->deriveClause($derivation, $environment);
    }

    /**
     * Writes the value as written; nothing when the attribute is given alone.
     */
    public function render(Output $out): void
    {
        $out->node($this->written);
    }
}
