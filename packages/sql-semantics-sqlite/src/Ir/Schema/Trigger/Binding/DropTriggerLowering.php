<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\DropTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\MissingTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\TriggerReference;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\TriggerVerification;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Resolution\TriggerNamespace;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Drop;
use SqlSemantics\Platform\Sqlite\Statement\Schema\SchemaObjectKind;

/**
 * Resolves the native installed or absent selector before publishing a removal.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DropTriggerLowering
{
    /**
     * Finds the selected declaration or proves the guarded absence within the known catalog.
     * @throws InvalidConstruction When a trigger selector is ambiguous or lacks enough catalog information
     */
    public function lower(BoundStatement $operation, Drop $drop): DropTrigger
    {
        Check::input($drop->object === SchemaObjectKind::Trigger, 'A trigger removal requires the DROP TRIGGER operation.');
        $context = $operation->context;
        $names = new TriggerNamespace();
        $names->validatePath($context);
        $schema = $drop->name->schema;
        $known = $schema === null ? null : $names->known($context, $schema);
        $path = $schema === null ? $context->searchPath : ($known === null ? [] : [$known]);
        foreach ($path as $location) {
            $matches = $context->triggersAt($drop->name->name, $location);
            Check::input(count($matches) <= 1, 'Conflicting declared triggers share the selected coordinate.');
            if ($matches !== []) {
                $trigger = $matches[0];

                return new DropTrigger(new TriggerReference(
                    new Identifier($trigger->schema->value),
                    new Identifier($trigger->name->value),
                    new Identifier($trigger->watchedSchema->value),
                    new Identifier($trigger->watchedName->value),
                ), $drop->ifExists);
            }
            Check::input($schema !== null || $context->complete, 'Unqualified DROP TRIGGER cannot skip an open schema.');
        }

        Check::input($context->complete && $drop->ifExists, 'DROP TRIGGER requires a resolved trigger or a known guarded absence.');
        $verification = match (true) {
            $schema === null => TriggerVerification::SearchPath,
            $known !== null => TriggerVerification::KnownSchema,
            default => TriggerVerification::UnknownSchema,
        };

        return new DropTrigger(new MissingTrigger(
            new Identifier($drop->name->name->value),
            $verification,
            $schema === null ? null : new Identifier($schema->value)
        ), true);
    }
}
