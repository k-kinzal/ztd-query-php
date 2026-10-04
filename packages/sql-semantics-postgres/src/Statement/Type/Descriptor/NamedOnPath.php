<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * The type a name denotes on the search path when the context cannot tell which catalog type that is.
 *
 * A column is declared with the type its type name resolves to. The name is
 * known; the catalog type behind it depends on types the context does not
 * declare: a user type, or a `pg_catalog` name that a type of an earlier
 * schema of the path may hide. The descriptor keeps the name as written and
 * the inputs that would settle it. Type modifiers of such a type are
 * interpreted by the type itself and are not part of the descriptor.
 * Source: https://www.postgresql.org/docs/17/ddl-schemas.html#DDL-SCHEMAS-PATH.
 *
 * @visibility public
 * @example Reading the declared type of a column of a user type
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
 *     $column = $semantics->analyze('CREATE TABLE person (current_mood mood)', [])->declarations()[0]->columns[0];
 *     [$column->type->name(), $column->type->missing[0]->describe()] // => ['mood', 'the definition of data type mood']
 */
final class NamedOnPath implements TypeDescriptor
{
    use Snapshot;

    /**
     * @var non-empty-list<MissingInput> The inputs that would settle which catalog type the name denotes
     */
    public readonly array $missing;

    /**
     * @param QualifiedName $name The type name as written
     * @param list<MissingInput> $missing The inputs that would settle which catalog type the name denotes; at least one
     */
    public function __construct(public readonly QualifiedName $name, array $missing)
    {
        $this->missing = Check::listOf($missing, MissingInput::class, 'A type known by name only depends on at least one missing input.', 1);
    }

    /**
     * Names the type as written: the parts joined by dots.
     */
    public function name(): string
    {
        $parts = [];
        foreach ([$this->name->catalog, $this->name->schema, $this->name->name] as $part) {
            if ($part !== null) {
                $parts[] = $part->value;
            }
        }

        return implode('.', $parts);
    }
}
