<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Validation;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\Facts;
use SqlSemantics\Platform\MySql\Model\Statement\Query;
use SqlSemantics\Platform\MySql\Model\Statement\Relation;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Platform;

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
        $graph = new ValueGraph(['SqlSemantics\\Platform\\MySql\\Model\\Statement\\', 'SqlSemantics\\Platform\\MySql\\Model\\Contract\\', $platform->statementNamespace()]);
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
