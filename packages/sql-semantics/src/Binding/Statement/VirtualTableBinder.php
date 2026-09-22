<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\Module;
use SqlSemantics\Model\Statement\Definition\CreateVirtualTableStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds a SQLite module invocation using the module constructor's documented text interface.
 * @visibility SqlSemantics
 */
final class VirtualTableBinder
{
    /**
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, Node $header, QueryContext $context): CreateVirtualTableStatement
    {
        $names = array_values(array_filter($header->children, static fn ($child): bool => $child instanceof Node && $child->name === 'nm'));
        if (count($names) !== 2) {
            throw new UnclassifiedSql('A virtual table requires a table name and module name.');
        }
        $module = $context->tables->identifiers->parts($names[1])[0];
        $arguments = [];
        foreach (Tree::outer($source, ['vtabarg']) as $argument) {
            if (Tree::hasTokens($argument)) {
                $arguments[] = new Module\ConstructorArgument(\SqlSemantics\Model\Validation\ModuleArgument::text($argument));
            }
        }
        return new CreateVirtualTableStatement($origin, ObjectBinder::name($header, $context), new Module\Invocation($module, $arguments), Tree::child($header, ['ifnotexists']) !== null);
    }
}
