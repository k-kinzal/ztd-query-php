<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * Resolves marker occurrences in retained structures before consumer preparation.
 *
 * Written positions come from structural rendering, never input SQL or parser
 * nodes. An unbound marker receives the same initial VARCHAR domain as ordinary
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
        $output = new Output(new \SqlSemantics\Platform\MySql\Rendering\Codec($context->profile->grammar));
        $node->render($output);
        $occurrences = $output->parameters();
        $settings = Settings::of($context);
        $default = Domain::string(16383, $settings->connection, coercibility: Coercibility::Coercible);
        $bindings = [];
        foreach ($occurrences as $position => $occurrence) {
            $bindings[$position] = $settings->parameters[$position] ?? $bound[$position] ?? $default;
        }
        return new Derivation($context->withSession($settings->withParameters($bindings)), parameters: $occurrences);
    }
}
