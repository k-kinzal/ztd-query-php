<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Validation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Facts;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Platform;

/**
 * Checks the internal statement graph and derives its binding facts.
 *
 * @visibility SqlSemantics
 */
final class Publication
{
    /**
     * Derives and verifies the facts covering every statement node.
     */
    public function facts(AnalysisContext $context, Statement $statement): Facts
    {
        $platform = Platform::shared();
        $graph = new ValueGraph(['SqlSemantics\\Platform\\PostgreSql\\Model\\Statement\\', 'SqlSemantics\\Platform\\PostgreSql\\Model\\Contract\\', $platform->statementNamespace()]);
        $objects = $graph->objects($statement);

        $derivation = new Derivation($context);
        $derivation->statement($statement);
        $facts = $derivation->facts();
        foreach ($objects as $object) {
            if ($object instanceof Scalar || $object instanceof Relation || $object instanceof Query) {
                Check::invariant($facts->covers($object), 'Derivation left a ' . $object::class . ' without facts.');
            }
        }
        $graph->objects($facts);

        return $facts;
    }
}
