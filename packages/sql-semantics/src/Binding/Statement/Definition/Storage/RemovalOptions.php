<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Storage;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds storage engine selection and completion policy in their release-specific grammar.
 * @visibility SqlSemantics
 */
final class RemovalOptions
{
    /**
     * Requires at most one engine declaration, including repeated identical names.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function engine(Node $source, Identifiers $identifiers): ?string
    {
        $options = Tree::outer($source, ['ts_option_engine', 'opt_ts_engine']);
        if (count($options) > 1) {
            throw new InvalidSql(InputViolation::StorageOption, $source);
        }
        if ($options === []) {
            return null;
        }
        $name = Tree::outer($options[0], ['ident_or_text'])[0] ?? throw new UnclassifiedSql('A storage engine option requires its name.');
        $engine = MySqlNames::read($name->tokens()[0], $identifiers);
        if ($engine === '') {
            throw new InvalidSql(InputViolation::StorageName, $name);
        }
        return $engine;
    }

    /**
     * Normalizes default waiting and preserves the legacy repeated-NO_WAIT rejection.
     * @throws InvalidSql
     */
    public static function waiting(Node $source): CompletionWait
    {
        $waiting = CompletionWait::Wait;
        foreach (Tree::outer($source, ['ts_option_wait', 'ts_wait']) as $option) {
            $next = CompletionWait::from(strtoupper(Tree::text($option)));
            if ($option->name === 'ts_wait' && $waiting === CompletionWait::NoWait && $next === CompletionWait::NoWait) {
                throw new InvalidSql(InputViolation::StorageOption, $option);
            }
            $waiting = $next;
        }
        return $waiting;
    }
}
