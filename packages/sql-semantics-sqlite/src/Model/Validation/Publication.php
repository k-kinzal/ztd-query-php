<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Validation;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Facts;
use SqlSemantics\Platform\Sqlite\Model\Statement\Query;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;
use SqlSemantics\Platform\Sqlite\Platform;

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
        $graph = new ValueGraph(['SqlSemantics\\Platform\\Sqlite\\Model\\Statement\\', 'SqlSemantics\\Platform\\Sqlite\\Model\\Contract\\', $platform->statementNamespace()]);
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
