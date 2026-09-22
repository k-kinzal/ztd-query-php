<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\SettingTokens;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value;
use SqlSemantics\Schema\Storage\Parameter;

/**
 * Reads the database's named storage-parameter mechanism, preserving typed values.
 *
 * @visibility SqlSemantics
 */
final class StorageParameters
{
    /**
     * @param list<string> $boundaries
     * @return list<Parameter>
     * @throws UnclassifiedSql
     */
    public static function read(Node $source, Scope $scope, array $boundaries = []): array
    {
        $result = [];
        foreach (Tree::outer($source, [...$boundaries, 'reloption_elem']) as $option) {
            if ($option->name !== 'reloption_elem') {
                continue;
            }
            $tokens = $option->tokens();
            $equals = array_search('=', array_map(static fn ($token): string => $token->text, $tokens), true);
            if ($equals === false) {
                throw new UnclassifiedSql('A storage parameter requires an explicit value.');
            }
            $name = $scope->identifiers->parts(new Node('parameter_name', 0, array_slice($tokens, 0, $equals)));
            $valueTokens = array_slice($tokens, $equals + 1);
            if ($valueTokens === []) {
                throw new UnclassifiedSql('A storage parameter requires an explicit value.');
            }
            $value = SettingTokens::value($valueTokens, $option, $scope);
            if (!$value instanceof Value\Literal && !$value instanceof Value\ConfigurationIdentifier && !$value instanceof Value\ConfigurationKeyword) {
                throw new UnclassifiedSql('Unclassified storage-parameter value.');
            }
            $result[] = new Parameter(new QualifiedName($name), $value);
        }
        return $result;
    }
}
