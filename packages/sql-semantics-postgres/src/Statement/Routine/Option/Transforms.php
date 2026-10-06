<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `TRANSFORM FOR TYPE type, ...`: the types whose transforms the routine applies.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Counting the transformed types
 *     $type = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('hstore')])));
 *     count((new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\Transforms([$type]))->types) // => 1
 */
final class Transforms implements RoutineOption
{
    use Snapshot;

    /**
     * @var non-empty-list<TypeName> The types in written order
     */
    public readonly array $types;

    /**
     * @param list<TypeName> $types The types in written order; at least one
     */
    public function __construct(array $types)
    {
        $this->types = Check::listOf($types, TypeName::class, 'TRANSFORM names at least one type.', 1);
    }

    /**
     * Tells that ALTER does not accept the option.
     */
    public function alterable(): bool
    {
        return false;
    }

    /**
     * Answers `transform`.
     */
    public function setting(): string
    {
        return 'transform';
    }

    /**
     * Derives the type modifiers.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->types as $type) {
            $type->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes TRANSFORM and each type after FOR TYPE.
     */
    public function render(Output $out): void
    {
        $out->keyword('TRANSFORM');
        foreach ($this->types as $position => $type) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->keyword('FOR', 'TYPE')->node($type);
        }
    }
}
