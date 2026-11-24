<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Facade\Index;

use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexDeclaration;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\StockIndexNames;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;

/**
 * Interprets declared relations and stock relations in the same namespace.
 *
 * @visibility SqlSemantics
 */
final class IndexBinding
{
    /**
     * Resolves one exact namespace coordinate and refuses wrong-kind or protected objects.
     */
    public function find(AnalysisContext $context, Name $schema, QualifiedName $name): ?IndexDeclaration
    {
        $matches = [];
        foreach ($context->indexNamespace->indexes as $index) {
            if ($index->schema->value === $schema->value && $index->index->value === $name->name->value) {
                $matches[] = $index;
            }
        }
        Check::input(
            $context->declared($name, $schema) === [] && !StockIndexNames::contains($context->profile->grammar, $schema, $name->name),
            'The selected relation is not an ordinary index.',
        );
        Check::input(count($matches) <= 1, 'The index selector cannot resolve conflicting index declarations.');
        $index = $matches[0] ?? null;
        if ($index !== null) {
            Check::input($index->ordinary, 'The selected index is protected in the ordinary index profile.');
            Check::input(count($context->declared(new QualifiedName($index->table), $index->schema)) === 1, 'The indexed relation must resolve without conflict.');
        }

        return $index;
    }

    /**
     * Distinguishes a known namespace from one that the search path alone names.
     */
    public function known(AnalysisContext $context, Name $schema): bool
    {
        if ($context->indexNamespace->known($schema)) {
            return true;
        }
        foreach ($context->tables as $relation) {
            if (($relation->name->schema ?? $context->declarationSchema)->value === $schema->value) {
                return true;
            }
        }

        return false;
    }
}
