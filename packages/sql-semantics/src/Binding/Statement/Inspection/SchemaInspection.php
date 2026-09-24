<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Inspection;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Classifies MySQL SHOW requests about schema objects, definitions, engines, and profiles.
 * @visibility SqlSemantics
 */
final class SchemaInspection
{
    /**
     * Returns null for dialects without SHOW and for SHOW forms owned by other families.
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::MySql) {
            return null;
        }
        $request = ShowRequest::of($node);
        if ($request === null) {
            return null;
        }
        return Listings::bind($origin, $request, $context)
            ?? Definitions::bind($origin, $request, $context)
            ?? Reports::bind($origin, $request, $context);
    }
}
