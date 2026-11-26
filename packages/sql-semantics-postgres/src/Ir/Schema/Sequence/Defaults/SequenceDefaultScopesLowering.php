<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaOwnerLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultScope;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\ForRoles;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\InSchemas;

/**
 * Binds the creator and schema scope of a native default sequence ACL.
 * @visibility SqlSemantics
 */
final class SequenceDefaultScopesLowering
{
    /**
     * Resolves each limiting clause and returns independent ordered operand lists.
     * @param list<DefaultScope> $scopes
     * @return array{list<SequencePrincipal>, list<Identifier>}
     */
    public function lower(AnalysisContext $context, array $scopes): array
    {
        $creators = [];
        $schemas = [];
        $owner = new SchemaOwnerLowering();
        $binding = new IndexBinding();
        foreach ($scopes as $scope) {
            if ($scope instanceof ForRoles) {
                Check::input($creators === [], 'A default sequence privilege has one creator clause.');
                foreach ($scope->roles as $role) {
                    $creators[] = new SequencePrincipal($owner->lower($context, $role));
                }
                continue;
            }
            Check::input($scope instanceof InSchemas && $schemas === [], 'A default sequence privilege has one schema clause.');
            foreach ($scope->schemas as $schema) {
                Check::input($binding->known($context, $schema), 'Default sequence privileges require a declared schema.');
                $schemas[] = new Identifier($schema->value);
            }
        }

        return [$creators, $schemas];
    }
}
