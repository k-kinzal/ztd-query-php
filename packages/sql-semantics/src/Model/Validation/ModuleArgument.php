<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\StatementList;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Dialect;

/**
 * Protects the boundary of one module constructor argument.
 * @visibility SqlSemantics
 */
final class ModuleArgument
{
    /**
     * @throws InvalidStructure
     */
    public static function check(string $text): void
    {
        try {
            $source = (new DialectParser(Dialect::Sqlite))->parse('CREATE VIRTUAL TABLE "__argument__" USING "__module__"(' . $text . ')');
        } catch (\SqlParser\Lexer\LexicalException|\SqlParser\Parser\SyntaxException $error) {
            throw new InvalidStructure('A module argument must remain within its SQL argument boundary.', 0, $error);
        }
        $statements = StatementList::read($source, Dialect::Sqlite);
        $arguments = Tree::outer($source, ['vtabarg']);
        if (count($statements) !== 1 || count($arguments) !== 1 || !Tree::hasTokens($arguments[0]) || self::text($arguments[0]) !== $text) {
            throw new InvalidStructure('A constructor argument must contain exactly one nonempty module argument.');
        }
    }

    /**
     * Returns the exact span SQLite passes to its module, excluding surrounding trivia.
     */
    public static function text(\SqlParser\Parser\Node $argument): string
    {
        $tokens = $argument->tokens();
        $first = array_shift($tokens);
        return ($first->text ?? '') . implode('', array_map(static fn ($token): string => $token->toString(), $tokens));
    }
}
