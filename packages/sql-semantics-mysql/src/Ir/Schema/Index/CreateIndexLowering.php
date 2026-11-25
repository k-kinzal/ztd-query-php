<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex as ParsedCreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

/**
 * Publishes the closed native index on its resolved table.
 *
 * @visibility SqlSemantics
 */
final class CreateIndexLowering
{
    /**
     * Publishes a complete native B-tree definition.
     */
    public function lower(BoundStatement $operation, ParsedCreateIndex $statement): CreateIndex
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input(
            $profile->parameters === ParameterStyle::Native && $profile->lexical->equals(new LexicalSettings())
                && $context->relationNames === Comparison::Sensitive && $context->columnNames === Comparison::AsciiInsensitive,
            'CREATE INDEX requires the default native profile.'
        );
        $visible = (new IndexOptionsLowering())->visible($statement);
        [$target, $parts, $hasIdentity] = (new IndexTableLowering())->resolve($operation, $statement);

        return new CreateIndex(
            $target,
            new Identifier($statement->name->value),
            $parts,
            $statement->kind === IndexKind::Unique,
            $visible,
            $profile->grammar,
            $hasIdentity
        );
    }
}
