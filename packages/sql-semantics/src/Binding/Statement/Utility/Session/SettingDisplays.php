<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility\Session;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\SettingTokens;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Statement\Configuration\Show;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds PostgreSQL SHOW to the displayed parameter name without reading its value.
 * @visibility SqlSemantics
 */
final class SettingDisplays
{
    /**
     * Maps the keyword forms to the parameter names the server displays for them; a parameter named all, in any case, displays every parameter as the server does.
     * @throws InvalidStructure
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): Show\ShowSettingStatement|Show\ShowAllSettingsStatement
    {
        $name = Tree::child($source, ['var_name']);
        if ($name !== null) {
            $parts = Collections::nonEmpty($context->tables->identifiers->parts($name));
            return count($parts) === 1 && strtolower($parts[0]) === 'all' ? new Show\ShowAllSettingsStatement($origin) : new Show\ShowSettingStatement($origin, $parts);
        }
        return match (implode(' ', array_slice(SettingTokens::words($source->tokens()), 1))) {
            'ALL' => new Show\ShowAllSettingsStatement($origin),
            'TIME ZONE' => new Show\ShowSettingStatement($origin, ['timezone']),
            'TRANSACTION ISOLATION LEVEL' => new Show\ShowSettingStatement($origin, ['transaction_isolation']),
            'SESSION AUTHORIZATION' => new Show\ShowSettingStatement($origin, ['session_authorization']),
            default => throw new UnclassifiedSql('Unclassified SHOW form: ' . Tree::text($source)),
        };
    }
}
