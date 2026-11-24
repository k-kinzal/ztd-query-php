<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\CreateSequence as ParsedCreateSequence;

/**
 * Publishes a fully resolved sequence declaration.
 *
 * @visibility SqlSemantics
 */
final class CreateSequenceLowering
{
    /**
     * Selects the local namespace and the exact generator policy.
     */
    public function lower(BoundStatement $operation, ParsedCreateSequence $statement): CreateSequence
    {
        $context = $operation->context;
        Check::input(
            $context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'CREATE SEQUENCE requires the native PostgreSQL sequence profile.'
        );
        $written = $statement->name->schema;
        $persistence = match ($statement->persistence) {
            Persistence::Permanent => $written?->value === 'pg_temp' ? SequencePersistence::Temporary : SequencePersistence::Permanent,
            Persistence::Unlogged => SequencePersistence::Unlogged,
            Persistence::Temporary, Persistence::Temp, Persistence::LocalTemporary, Persistence::LocalTemp => SequencePersistence::Temporary,
            Persistence::GlobalTemporary, Persistence::GlobalTemp => SequencePersistence::GlobalTemporary,
        };
        $namespace = $written ?? ($persistence->temporary() ? new Name('pg_temp') : $context->declarationSchema);
        Check::input($namespace->value === 'pg_temp' || (new IndexBinding())->known($context, $namespace), 'CREATE SEQUENCE requires a declared local namespace.');
        $name = new SequenceName(new Identifier($namespace->value), new Identifier($statement->name->name->value));
        $parameters = (new SequenceOptionsLowering())->lower($context, $statement->options);

        return new CreateSequence($name, $parameters, $persistence, $statement->ifNotExists, $context->profile->grammar);
    }
}
