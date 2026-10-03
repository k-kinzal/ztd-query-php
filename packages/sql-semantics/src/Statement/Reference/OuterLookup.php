<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;

/**
 * A lexical fallback that is used only if missing local declarations supply no matching column.
 * @visibility public
 * @example Retaining an outer lookup rather than assuming that an undeclared local table wins
 *     $scope = new \SqlSemantics\Statement\Relation\Scope(new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main'))));
 *     (new \SqlSemantics\Statement\Reference\OuterLookup($scope, new \SqlSemantics\Statement\Identifier\Name('foo')))->resolution // => \SqlSemantics\Statement\Reference\MissingColumn::Value
 */
final class OuterLookup
{
    /**
     * The actual outer resolution, including another incomplete namespace or an ambiguity.
     */
    public readonly ResolvedColumn|CandidateColumn|MissingColumn|AmbiguousColumn|AmbiguousTable|NamedAlias $resolution;

    /**
     * Derives the fallback from its exact lexical scope instead of accepting an unrelated column pointer.
     */
    public function __construct(public readonly Scope|SqliteAliasScope $scope, public readonly Name $name, public readonly ?QualifiedName $qualifier = null)
    {
        $this->resolution = $scope->resolve($name, $qualifier);
    }
}
