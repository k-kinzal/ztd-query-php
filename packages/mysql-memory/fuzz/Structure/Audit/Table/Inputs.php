<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Table;

use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

/**
 * Reads fixture identities from the original semantic operands.
 * No reference rendering, parser node or execution result supplies a replacement.
 */
final class Inputs
{
    /**
     * @return list<Node|QualifiedName> Owned semantic operands and object names
     */
    public function values(Node $root): array
    {
        $found = [];
        $pending = [$root];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof QualifiedName) {
                $found[] = $value;
            } elseif ($value instanceof Node) {
                $found[] = $value;
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
        return $found;
    }

    /**
     * Matches the missing identity without splitting names that themselves contain dots.
     */
    public function missing(Node $node, string $message, string $database): ?QualifiedName
    {
        $matches = [];
        foreach ($this->values($node) as $value) {
            if (!$value instanceof QualifiedName) {
                continue;
            }
            $schema = $value->schema ?? new Name($database);
            if ($schema->value !== '' && $message === "Table '" . $schema->value . '.' . $value->name->value . "' doesn't exist") {
                $matches[serialize([$schema->value, $value->name->value])] = new QualifiedName($value->name, $schema);
            }
        }
        return count($matches) === 1 ? array_values($matches)[0] : null;
    }

    /**
     * @return non-empty-list<Name> Requested column identities, or an independent fixture column
     */
    public function columns(Node $node): array
    {
        $columns = [];
        foreach ($this->values($node) as $value) {
            if ($value instanceof ColumnUse) {
                $columns[mb_strtolower($value->name->value, 'UTF-8')] ??= $value->name;
            } elseif ($value instanceof ColumnPart) {
                $columns[mb_strtolower($value->column->value, 'UTF-8')] ??= $value->column;
            }
        }
        return $columns === [] ? [new Name('fixture_value')] : array_values($columns);
    }
}
