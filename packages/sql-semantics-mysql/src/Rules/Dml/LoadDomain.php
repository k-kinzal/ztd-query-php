<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * Intersects LOAD source features with the releases that interpret their options.
 *
 * S3, PARALLEL and MEMORY require 8.2 or later.
 * Compression requires 9.x. A conflicting bulk annotation can be interpreted
 * only in 8.0 or 8.1, where it is ignored; combining it with these newer features
 * has no language interpretation. This does not inspect database or file state.
 * Observed through Testcontainers on 8.0, 8.1, 8.2, 8.3, 8.4 and 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LoadDomain
{
    /**
     * Answers whether the source operands require active bulk option semantics.
     */
    public function modern(LoadInput $input, ?BulkOptions $bulk, ?Text $compression): bool
    {
        return $input->source === LoadSource::S3 || $bulk?->parallel !== null || $bulk?->memory !== null || $compression !== null;
    }

    /**
     * Answers whether the LOAD operands share at least one supported interpretation.
     */
    public function compatible(LoadInput $input, ?BulkOptions $bulk, ?Text $compression, ?LoadConflict $conflict): bool
    {
        if ($conflict !== null && $this->modern($input, $bulk, $compression)) {
            return false;
        }
        return $compression === null || $input->count === null || trim($input->count->text, '0') !== '';
    }
}
