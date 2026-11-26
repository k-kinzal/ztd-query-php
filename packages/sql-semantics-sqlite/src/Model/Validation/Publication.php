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
     * Audits the whole syntax graph and derives facts for the nodes SQLite binds.
     */
    public function facts(AnalysisContext $context, Statement $statement): Facts
    {
        $platform = Platform::shared();
        $graph = new ValueGraph(['SqlSemantics\\Platform\\Sqlite\\Model\\Statement\\', 'SqlSemantics\\Platform\\Sqlite\\Model\\Contract\\', $platform->statementNamespace()]);
        $objects = $graph->objects($statement);
        $discarded = $platform->discardedBindings($objects);
        if ($discarded !== []) {
            $objects = $graph->objects($statement, $discarded);
        }

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
