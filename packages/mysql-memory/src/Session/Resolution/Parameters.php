<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Node;

/**
 * Resolves marker occurrences in retained structures before consumer preparation.
 *
 * Written positions belong to the parameter occurrences; no renderer or parser
 * discovers them. An unbound marker receives the same initial VARCHAR domain as ordinary
 * prepared input. Previously supplied parameter domains remain authoritative.
 *
 * @visibility MySqlMemory
 */
final class Parameters
{
    /**
     * Builds a derivation with occurrence identities and initial preparation domains.
     *
     * @param array<int, Domain> $bound Previously supplied marker domains
     */
    public function derivation(Node $node, AnalysisContext $context, array $bound = []): Derivation
    {
        $occurrences = (new \MySqlMemory\Evaluation\Compile\Walker())->find($node, \SqlSemantics\Platform\MySql\Statement\Literal\Parameter::class);
        $settings = Settings::of($context);
        $default = Domain::string(16383, $settings->connection, coercibility: Coercibility::Coercible);
        $bindings = [];
        $unbound = [];
        foreach ($occurrences as $occurrence) {
            $position = $occurrence->position;
            $bindings[$position] = $settings->parameters[$position] ?? $bound[$position] ?? $default;
            if ((!isset($settings->parameters[$position]) && !isset($bound[$position])) || in_array($position, $settings->unboundParameters, true)) {
                $unbound[] = $position;
            }
        }
        return new Derivation($context->withSession($settings->withParameters($bindings, $unbound)));
    }
}
