<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\TypeLookup;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * An attribute value read as a type, such as the argument type of an operator or the state type of an aggregate.
 *
 * `defGetTypeName` accepts a type name, or a string or reserved keyword,
 * which names a type with exactly that one name.
 * Source: https://www.postgresql.org/docs/17/sql-createaggregate.html, `defGetTypeName` in `src/backend/commands/define.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading the state type of an aggregate in a complete context
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE AGGREGATE total (int4) (sfunc = int4pl, stype = int4)', []);
 *     $state = $operation->statement->definition[1]->value;
 *     $state->typeFact($operation->context)->descriptor->name() // => 'integer'
 */
final class TypeArgument implements AttributeArgument
{
    use Snapshot;

    /**
     * @param TypeName|StringConstant|KeywordWord $type The type as written
     */
    public function __construct(public readonly TypeName|StringConstant|KeywordWord $type)
    {
    }

    /**
     * Answers the type the value denotes in a context.
     */
    public function typeFact(AnalysisContext $context): TypeFact
    {
        if ($this->type instanceof TypeName) {
            return $this->type->typeFact($context);
        }

        $found = (new TypeLookup())->find($context, new DottedName([$this->type instanceof StringConstant ? new Name($this->type->value) : $this->type->word]));

        return $found instanceof Builtin ? new Known($found) : $found;
    }

    /**
     * Tells whether the reading reads a type.
     */
    public function fits(Reading $reading): bool
    {
        return $reading === Reading::Type;
    }

    /**
     * Derives the modifier expressions of the type name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->type->deriveClause($derivation, $environment);
    }

    /**
     * Writes the type as written.
     */
    public function render(Output $out): void
    {
        $out->node($this->type);
    }
}
