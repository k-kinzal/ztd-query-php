<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key;

use SqlParser\Lexer\SourceException;
use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\InputTokens;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexParser;

/**
 * Retains completed full-text parser dependencies before a later input refusal.
 *
 * The consumer resolves plugin availability in its server state. These
 * semantic operands retain neither the input text nor parser nodes.
 *
 * @visibility public
 * @example Reading a dependency before an invalid algorithm name
 *     $parser = new \SqlParser\MySql\MySqlParser('mysql-8.4.7');
 *     $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
 *     (new \SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\SyntaxParsers())->before($parser, 'CREATE FULLTEXT INDEX i ON t(a) WITH PARSER zz ALGORITHM=bad', 60, $profile)[0][1]->parser->value // => 'zz'
 */
final class SyntaxParsers
{
    /**
     * Lowers each reached WITH PARSER operand and its completion position.
     *
     * @return list<array{int, IndexParser, \SqlSemantics\Statement\Identifier\QualifiedName|null}>
     */
    public function before(ReductionParser $parser, string $statement, int $end, LanguageProfile $profile): array
    {
        $tokens = (new InputTokens())->prefix($parser, $statement);
        $statement = substr($statement, 0, $tokens[count($tokens) - 1]->offset);
        $lowering = new Lowering(Productions::load(dirname(__DIR__, 4) . '/resources/productions/' . $profile->grammar->value . '.php'), new Leaves(), $profile);
        $dependencies = [];
        $table = null;
        try {
            $parser->parse($statement, static function (Node $node) use ($lowering, $end, &$dependencies, &$table): void {
                if ($node->name === 'table_ident') {
                    $table = $lowering->names->qualified($node);
                }
                if (!in_array($node->name, ['fulltext_key_opt', 'fulltext_index_option'], true)) {
                    return;
                }
                $range = (new SourceRanges())->span($node);
                if ($range === null || $range[1] > $end) {
                    return;
                }
                $option = (new KeyOptionRule($lowering))->option($node);
                if ($option instanceof IndexParser) {
                    $dependencies[] = [$range[1], $option, $table];
                }
            });
        } catch (SourceException) {
            // Completed dependencies precede the caller's original input refusal.
        }

        return $dependencies;
    }
}
