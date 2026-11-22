<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\TypeFact;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\Modifiers;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\TypeLookup;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;

/**
 * A type designated by its catalog name, optionally schema-qualified and with type modifiers.
 *
 * Rule: PG-TYPE-NAMED-001. The name is looked up by PG-TYPE-LOOKUP-001: a
 * `pg_catalog` type is known, any other type needs a declaration the context
 * cannot hold. Modifiers follow PG-TYPE-MODIFIER-001.
 * Source: https://www.postgresql.org/docs/17/sql-createtype.html, https://www.postgresql.org/docs/17/datatype.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class NamedDesignation implements TypeDesignation
{
    use Snapshot;

    /**
     * @var list<Scalar> The modifier expressions in order
     */
    public readonly array $modifiers;

    /**
     * @param DottedName $name The type name
     * @param list<Scalar> $modifiers The modifier expressions in order
     */
    public function __construct(public readonly DottedName $name, array $modifiers = [])
    {
        $this->modifiers = Check::listOf($modifiers, Scalar::class, 'Type modifiers are expressions.');
    }

    /**
     * Looks the name up and applies the modifiers.
     */
    public function typeFact(AnalysisContext $context, bool $constant): TypeFact
    {
        $found = (new TypeLookup())->find($context, $this->name);

        return $found instanceof Builtin ? (new Modifiers())->apply($found, $this->modifiers) : $found;
    }

    /**
     * Answers the last part of the name.
     */
    public function catalogName(): Name
    {
        return $this->name->last();
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
     * Writes the name and the modifiers.
     */
    public function render(Output $out): void
    {
        (new Spelling())->dotted($out, $this->name->parts, NameUse::Routine);
        if ($this->modifiers !== []) {
            $out->symbol('(')->list($this->modifiers)->symbol(')');
        }
    }
}
