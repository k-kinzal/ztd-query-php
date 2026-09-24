<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes the DefineStmt forms by the object class after CREATE [OR REPLACE].
 * @visibility SqlSemantics
 */
final class Definitions
{
    /**
     * Returns null for object classes defined elsewhere.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $words = ObjectAddresses::words($source);
        $class = ($words[1] ?? '') === 'OR' ? ($words[3] ?? '') : ($words[1] ?? '');
        return match ($class) {
            'TYPE' => TypeDefinitions::create($origin, $source, $context),
            'OPERATOR' => Operators::create($origin, $source, $context),
            'AGGREGATE' => Aggregates::create($origin, $source, $context),
            'COLLATION' => Locales::collation($origin, $source, $context),
            'TEXT' => TextSearch::create($origin, $source, $context),
            default => null,
        };
    }
}
