<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Reference;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A cast named by its source and target types: `( source AS target )`.
 *
 * Source: https://www.postgresql.org/docs/17/sql-dropcast.html.
 *
 * @visibility public
 * @example Reading the target type
 *     $text = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('text')])));
 *     $mood = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('mood')])));
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\CastPair($text, $mood))->target === $mood // => true
 */
final class CastPair implements ObjectReference
{
    use Snapshot;

    /**
     * @param TypeName $source The source type
     * @param TypeName $target The target type
     */
    public function __construct(public readonly TypeName $source, public readonly TypeName $target)
    {
    }

    /**
     * Derives the type modifiers.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->source->deriveClause($derivation, $environment);
        $this->target->deriveClause($derivation, $environment);
    }

    /**
     * Writes the types in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->source)->keyword('AS')->node($this->target)->symbol(')');
    }
}
