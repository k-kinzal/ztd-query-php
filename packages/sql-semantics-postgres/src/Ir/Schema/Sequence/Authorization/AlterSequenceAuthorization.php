<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\Logging\SequenceLoggingPrefix;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Changes the authorization and optional logging mode of one resolved sequence.
 * @visibility public
 * @example Assigning a declared owning role
 *     $identifier = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $source = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector(new $identifier('ticket'), new $identifier('public'));
 *     $owner = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal(new $identifier('archive owner'));
 *     $action = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequenceAuthorizationAction($owner);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\AlterSequenceAuthorization($source, [$action]))->toString() // => 'ALTER SEQUENCE "public"."ticket" OWNER TO "archive owner"'
 */
final class AlterSequenceAuthorization implements SemanticsIR
{
    use Snapshot;

    /**
     * @var list<SequenceAuthorizationAction>
     */
    public readonly array $actions;

    /**
     * Checks the detached action roles with their common source and release.
     *
     * @param list<SequenceAuthorizationAction> $actions
     */
    public function __construct(
        public readonly SequenceSelector $source,
        array $actions,
        public readonly bool $ifExists = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $source->checkProfile($release);
        $detached = Check::listOf($actions, SequenceAuthorizationAction::class, 'Sequence authorization needs an ordered action list.');
        if ($source->presence === SequencePresence::Existing) {
            $logging = [];
            $principal = false;
            foreach ($detached as $action) {
                if ($action->value instanceof SequenceLogging) {
                    $logging[] = $action->value;
                } else {
                    $action->value->checkRelease($release);
                    $principal = true;
                }
            }
            Check::input($principal, 'A present sequence authorization requires an owning role assignment.');
            Check::input($logging === [] || $source->schema?->value !== 'pg_temp', 'Changing sequence logging requires a persistent source.');
            SequenceLoggingPrefix::check($logging);
        } else {
            Check::input($ifExists, 'An absent sequence selector requires IF EXISTS.');
            Check::input($detached === [], 'An absent sequence cannot carry authorization actions.');
        }
        $this->actions = $detached;
    }

    /**
     * Identifies the exact native release and lexical profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the checked action list or the inert spelling of a guarded no-op.
     */
    public function toString(): string
    {
        $clauses = [];
        foreach ($this->actions as $action) {
            $clauses[] = $action->toString();
        }

        return 'ALTER SEQUENCE ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->source->quoted() . ' '
            . ($clauses === [] ? 'OWNER TO CURRENT_USER' : implode(', ', $clauses));
    }
}
