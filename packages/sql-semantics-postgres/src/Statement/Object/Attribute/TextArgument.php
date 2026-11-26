<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * An attribute value read as text, such as the initial condition of an aggregate or the locale of a collation.
 *
 * `defGetString` accepts every value: a string as written, a word or a type
 * name as its names joined by dots, a keyword in lower case, an operator, or
 * a number as written. The text is opaque to the command; an initial
 * condition, for example, is later read by the input function of the state
 * type.
 * Source: https://www.postgresql.org/docs/17/sql-createaggregate.html, `defGetString` in `src/backend/commands/define.c` of PostgreSQL 17.
 *
 * @visibility SqlSemantics
 */
final class TextArgument implements AttributeArgument
{
    use Snapshot;

    /**
     * @param TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant $text The value as written
     */
    public function __construct(public readonly TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant $text)
    {
    }

    /**
     * Tells whether the reading reads text.
     */
    public function fits(Reading $reading): bool
    {
        return $reading === Reading::Text;
    }

    /**
     * Derives the modifier expressions of a value written as a type name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->text->deriveClause($derivation, $environment);
    }

    /**
     * Writes the value as written.
     */
    public function render(Output $out): void
    {
        $out->node($this->text);
    }
}
