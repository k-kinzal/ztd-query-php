<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\StockIndexNames;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The resolved coordinate or checked absence of one sequence selector.
 *
 * @visibility public
 * @example An absolute sequence selector
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('ticket'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('public')))->quoted() // => '"public"."ticket"'
 */
final class SequenceSelector
{
    use Snapshot;

    /**
     * Checks which qualified coordinate is required by the resolved role.
     */
    public function __construct(
        public readonly Identifier $name,
        public readonly ?Identifier $schema,
        public readonly SequencePresence $presence = SequencePresence::Existing,
    ) {
        Check::input($schema !== null || $presence === SequencePresence::MissingSequence, 'A sequence selector requires an absolute namespace.');
        Check::input(
            $schema === null || ($schema->value !== 'pg_toast' && !IndexSchemas::sessionInternal($schema->value)),
            'A sequence selector cannot use a physical TOAST or temporary schema.'
        );
        if ($presence === SequencePresence::MissingNamespace) {
            Check::input(
                $schema->value !== 'pg_temp' && !IndexSchemas::stock($schema->value),
                'A missing sequence namespace must be an application namespace.'
            );
        }
    }

    /**
     * Emits the checked qualification, or the unqualified absence selector.
     */
    public function quoted(): string
    {
        return ($this->schema === null ? '' : $this->schema->quoted() . '.') . $this->name->quoted();
    }

    /**
     * Applies the exact release's stock relation occupancy.
     */
    public function checkProfile(GrammarRelease $release): void
    {
        $namespace = $this->schema->value ?? 'pg_catalog';
        Check::input(
            !StockIndexNames::contains($release, new Name($namespace), new Name($this->name->value)),
            'A stock non-sequence relation cannot be a sequence target.'
        );
    }
}
