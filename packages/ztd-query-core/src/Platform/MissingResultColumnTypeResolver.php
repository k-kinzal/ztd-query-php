<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Schema\ColumnDeclaration;

/**
 * The resolver a session has when no platform gave it one.
 *
 * It answers nothing, on purpose. A session that can read rows but cannot say
 * what any column is has been built without a platform, and refusing at the
 * first question is what makes that visible.
 */
final class MissingResultColumnTypeResolver implements ResultColumnTypeResolver
{
    /**
     * Refuses to answer, because nothing has said how this platform reports types.
     *
     * A session built without a platform resolver can read rows but cannot say
     * what any column is, and guessing would be worse than refusing.
     *
     * @param array<string, mixed> $metadata Column metadata as the driver reported it
     *
     * @return ColumnDeclaration Never; the call is always refused
     *
     * @throws InvalidDefinitionException Always
     */
    public function resolve(array $metadata): ColumnDeclaration
    {
        throw new InvalidDefinitionException(
            'A database platform result column type resolver is required.',
        );
    }
}
