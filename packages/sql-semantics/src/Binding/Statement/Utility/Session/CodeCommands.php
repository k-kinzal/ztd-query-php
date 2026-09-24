<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility\Session;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Binding\Statement\Utility\OptionWords;
use SqlSemantics\Binding\Statement\Utility\StringConstants;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Execution\DoBlockStatement;
use SqlSemantics\Model\Statement\Loading\LoadLibraryStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds PostgreSQL LOAD and DO, which name code the server loads or runs; binding neither loads nor runs it.
 * @visibility SqlSemantics
 */
final class CodeCommands
{
    /**
     * Records the library file of LOAD.
     * @throws InvalidStructure
     * @throws UnclassifiedSql
     */
    public static function load(Origin $origin, Node $source): LoadLibraryStatement
    {
        return new LoadLibraryStatement($origin, StringConstants::literal(Tree::outer($source, ['Sconst'])[0] ?? throw new UnclassifiedSql('LOAD requires its file name.')));
    }

    /**
     * Requires exactly one code block and at most one language, as the server's option reader does.
     * @throws InvalidSql
     * @throws InvalidStructure
     * @throws UnclassifiedSql
     */
    public static function block(Origin $origin, Node $source, QueryContext $context): DoBlockStatement
    {
        $codes = [];
        $languages = [];
        foreach (Tree::outer($source, ['dostmt_opt_item']) as $item) {
            $language = Tree::child($item, ['NonReservedWord_or_Sconst']);
            if ($language === null) {
                $codes[] = StringConstants::literal(Tree::child($item, ['Sconst']) ?? throw new UnclassifiedSql('A DO item requires its code.'));
                continue;
            }
            $token = $language->tokens()[0] ?? throw new UnclassifiedSql('DO LANGUAGE requires its name.');
            $languages[] = OptionWords::word($token, $context->tables->identifiers);
        }
        if (count($codes) !== 1 || count($languages) > 1 || in_array('', $languages, true)) {
            throw new InvalidSql(InputViolation::AnonymousBlock, $source);
        }
        return new DoBlockStatement($origin, $codes[0], $languages[0] ?? null);
    }
}
