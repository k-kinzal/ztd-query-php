<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlObject;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes MySQL CREATE and ALTER requests for tablespaces, logfile groups, servers, views, and loadable functions.
 * @visibility SqlSemantics
 */
final class ObjectDefinitions
{
    /**
     * Returns null for statements outside these MySQL object definitions.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     * @throws \SqlSemantics\SemanticException
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $words = array_map(static fn ($token): string => strtoupper($token->text), array_slice($source->tokens(), 0, 4));
        if ($origin->dialect !== Dialect::MySql || !in_array($words[0] ?? '', ['CREATE', 'ALTER'], true)) {
            return null;
        }
        $identifiers = $context->tables->identifiers;
        return match (true) {
            ($words[1] ?? '') === 'TABLESPACE', ($words[1] ?? '') === 'UNDO' && ($words[2] ?? '') === 'TABLESPACE' => Tablespaces::bind($origin, $source, $words, $identifiers),
            ($words[1] ?? '') === 'LOGFILE' => LogfileGroups::bind($origin, $source, $words[0] === 'CREATE', $identifiers),
            ($words[1] ?? '') === 'SERVER' => Servers::bind($origin, $source, $words[0] === 'CREATE', $identifiers),
            $words[0] === 'ALTER' => ViewAlterations::bind($origin, $source, $context),
            default => LoadableFunctions::bind($origin, $source, $identifiers),
        };
    }
}
