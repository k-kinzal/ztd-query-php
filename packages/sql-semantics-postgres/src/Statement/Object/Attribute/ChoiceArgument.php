<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ArgumentText;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Choice;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * An attribute value read as one of a fixed set of words, such as the `parallel` mode of an aggregate.
 *
 * The command reads the value as text and compares it with the words of the
 * set; the member is the word the text names.
 * Source: https://www.postgresql.org/docs/17/sql-createaggregate.html, https://www.postgresql.org/docs/17/sql-createtype.html.
 *
 * @visibility public
 * @example Reading the parallel mode of an aggregate
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE AGGREGATE total (int4) (sfunc = int4pl, stype = int4, parallel = SAFE)');
 *     $operation->statement->definition[2]->value->choice // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Parallelism::Safe
 * @example Rejecting a member the text does not name
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\ChoiceArgument(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('safe'), \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Parallelism::Unsafe) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ChoiceArgument implements AttributeArgument
{
    use Snapshot;

    /**
     * @param TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant $written The value as written
     * @param Choice $choice The member the text names
     */
    public function __construct(public readonly TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant $written, public readonly Choice $choice)
    {
        Check::input($choice::read((new ArgumentText())->text($written)) === $choice, 'The member of a choice is the one the written text names.');
    }

    /**
     * Tells whether the reading compares the text with the set of this member.
     */
    public function fits(Reading $reading): bool
    {
        return $reading->choices() === $this->choice::class;
    }

    /**
     * Derives the modifier expressions of a value written as a type name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->written->deriveClause($derivation, $environment);
    }

    /**
     * Writes the value as written.
     */
    public function render(Output $out): void
    {
        $out->node($this->written);
    }
}
