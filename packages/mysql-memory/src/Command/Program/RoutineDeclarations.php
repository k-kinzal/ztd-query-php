<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Declared;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Resolves the typed declarations preceding a routine body, including refused input.
 *
 * @visibility MySqlMemory
 */
final class RoutineDeclarations
{
    /**
     * Lists parameters followed by a stored function's return declaration.
     *
     * @return list<array{DeclaredName, ?CollationName}>
     */
    public function items(CreateProcedure|CreateFunction|RefusedProgramBody $statement): array
    {
        $types = array_map(static fn ($parameter): array => [$parameter->type, $parameter->collation], $statement->parameters->parameters ?? []);
        if (!$statement instanceof CreateProcedure && $statement->returns !== null) {
            $types[] = [$statement->returns, $statement->collation];
        }
        return $types;
    }

    /**
     * Checks declaration types using the routine database and SQL mode.
     *
     * @param list<array{DeclaredName, ?CollationName}> $types Declarations in input order
     * @throws \MySqlMemory\Error\SqlError When a declaration contradicts the current SQL mode or database
     */
    public function check(QualifiedName $name, array $types, Session $session, Context $context): void
    {
        $settings = $session->settings();
        $database = ProgramSource::database($name->schema, $session);
        $declared = new Declared($settings->resolution()->schema($database), $settings->release());
        foreach ($types as [$type, $collationName]) {
            $collation = $collationName?->name === null ? null : Collation::named($collationName->name->value);
            $domain = $declared->domain($type, $collation);
            if ($collation !== null) {
                $domain = $domain->withCollation($collation, $domain->coercibility);
            }
            (new \MySqlMemory\Command\Definition\EnumerationMembers())->check($domain, '', $context);
        }
    }
}
