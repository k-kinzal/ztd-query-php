<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * Reads the named target and correlation name shared by insertion, update, and deletion syntax.
 * @visibility SqlSemantics
 */
final class QualifiedTableReader
{
    /**
     * Reads namespace positions separately from an optional destination alias.
     */
    public function read(Node $source, Catalog $catalog): TableReference
    {
        $names = (new IdentifierReader())->directNames($source);
        $hasAlias = array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Token && $child->name === 'AS') !== [];
        $alias = $hasAlias ? array_pop($names) : null;
        assert(count($names) === 1 || count($names) === 2, 'A DML target has one name and an optional database.');
        return new TableReference($catalog, count($names) === 1 ? new QualifiedName($names[0]) : new QualifiedName($names[1], $names[0]), $alias);
    }
}
