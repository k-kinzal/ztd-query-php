<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Resolution;

use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;

/**
 * Selects the owning schema and the watched relation before resolving the body.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerNamespace
{
    /**
     * Finds the native trigger owner, including implicit promotion to temp.
     */
    public function owner(AnalysisContext $context, ?Name $specified, bool $temporary, QualifiedName $watched): Name
    {
        $temp = new Name('temp');
        if ($temporary) {
            return $temp;
        }
        if ($specified !== null) {
            return $specified;
        }
        if ($watched->schema !== null ? Comparison::AsciiInsensitive->equal($watched->schema->value, 'temp')
            : $context->declared($watched, $temp) !== []) {
            return $temp;
        }

        return new Name('main');
    }

    /**
     * Rejects a trigger namespace with a non-native order or duplicate schema names.
     */
    public function validatePath(AnalysisContext $context): void
    {
        $comparison = Comparison::AsciiInsensitive;
        $path = $context->searchPath;
        Check::input(
            $context->relationNames === $comparison && isset($path[1])
                && $comparison->equal($path[0]->value, 'temp') && $comparison->equal($path[1]->value, 'main'),
            'Trigger resolution requires SQLite comparison and schema search order.',
        );
        $seen = [];
        foreach ($path as $schema) {
            $key = $comparison->fold($schema->value);
            Check::input(!isset($seen[$key]), 'Trigger resolution requires distinct schema names.');
            $seen[$key] = true;
        }
    }

    /**
     * Returns the catalog spelling of a named schema, if attached.
     */
    public function known(AnalysisContext $context, Name $name): ?Name
    {
        foreach ($context->searchPath as $schema) {
            if (Comparison::AsciiInsensitive->equal($schema->value, $name->value)) {
                return $schema;
            }
        }

        return null;
    }

    /**
     * Limits an unqualified watched relation to a stored trigger's owner.
     */
    public function watched(QualifiedName $watched, Name $owner): QualifiedName
    {
        return $watched->schema === null && !Comparison::AsciiInsensitive->equal($owner->value, 'temp')
            ? new QualifiedName($watched->name, $owner)
            : $watched;
    }
}
