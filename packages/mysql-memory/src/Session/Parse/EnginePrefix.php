<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Command\Show\Server\ServerCatalog;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;

/**
 * Recovers completed table options before a later syntax error in a legacy statement.
 *
 * MySQL 5.6 and 5.7 resolve an engine name while parsing it. An unknown ENGINE before
 * an unsupported tail therefore warns or fails before the syntax error. A prefix that
 * itself parses as a complete table statement is parsed and resolved normally; no table
 * command is executed. Incomplete prefixes are left to the original syntax diagnostic.
 * Verified with CREATE TABLE ... ENGINE=bad START TRANSACTION on both releases.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html#sqlmode_no_engine_substitution.
 *
 * @visibility MySqlMemory
 */
final class EnginePrefix
{
    /**
     * Answers the unknown engine conditions in a complete prefix, by source offset.
     *
     * @param list<Token> $tokens The original statement tokens
     * @return array<int, SqlError>
     */
    public function before(array $tokens, string $statement, int $end, Session $session): array
    {
        if (!in_array(strtoupper($tokens[0]->text ?? ''), ['CREATE', 'ALTER'], true)) {
            return [];
        }
        try {
            $semantics = $session->semantics();
            $tree = $semantics->parser()->parse(substr($statement, 0, $end));
            $operation = $semantics->analyze($tree);
        } catch (SourceException|AnalysisException|\SqlSemantics\Construction\RejectedInput) {
            return [];
        }
        if (!$operation->statement instanceof CreateTable && !$operation->statement instanceof AlterTable) {
            return [];
        }
        $errors = [];
        foreach ((new Walker())->find($operation->statement, EngineOption::class) as $option) {
            $source = $operation->sources->of($option);
            if ($source !== null && ServerCatalog::shared()->engine($option->engine->value) === null) {
                $errors[$source->offset] = SchemaError::UnknownStorageEngine->error($option->engine->value);
            }
        }
        ksort($errors);

        return $errors;
    }
    /**
     * Separates recoverable engine warnings from the first engine error that ends parsing.
     *
     * @param list<Token> $tokens The original statement tokens
     * @return array{int, array<int, SqlError>, SqlError} Parsing boundary, warnings and final error
     */
    public function conditions(array $tokens, string $statement, int $end, Session $session, SqlError $fallback): array
    {
        $engines = $this->before($tokens, $statement, $end, $session);
        if ($engines === [] && $session->settings()->legacy()) {
            $engines = $this->completed($statement, $end, $session);
        }
        $first = array_key_first($engines);
        if ($first === null || !$session->modes()->has('NO_ENGINE_SUBSTITUTION')) {
            return [$end, $engines, $fallback];
        }

        return [min($end, $first), [], $engines[$first]];
    }

    /**
     * Resolves completed table and partition engine operands before a contextual definition refusal.
     *
     * A complete parser input is required on modern releases; legacy lookups
     * occur during parsing and survive a later syntax error.
     * Only lowered names and their diagnostic boundaries leave the parser callback.
     *
     * @return array<int, SqlError> Unknown engines by completion boundary
     */
    public function completed(string $statement, int $end, Session $session): array
    {
        $parser = $session->semantics()->parser();
        \SqlSemantics\Diagnostic\Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'Engine operands come from completed grammar productions.');
        $profile = $session->semantics()->profile();
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering((new \SqlSemantics\Platform\MySql\Platform())->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        $errors = [];
        try {
            $parser->parse($statement, static function (\SqlParser\Parser\Node $node) use ($lowering, $end, &$errors): void {
                if (!in_array($node->name, ['create_table_option', 'opt_part_option', 'part_option'], true)) {
                    return;
                }
                if (!in_array($lowering->form($node)->signature, ['create_table_option: ENGINE_SYM opt_equal storage_engines', 'create_table_option: ENGINE_SYM opt_equal ident_or_text', 'opt_part_option: opt_storage ENGINE_SYM opt_equal storage_engines', 'part_option: opt_storage ENGINE_SYM opt_equal ident_or_text'], true)) {
                    return;
                }
                $offset = (new \SqlSemantics\Construction\SourceRanges())->span($node)[1] ?? 0;
                if ($offset > $end) {
                    return;
                }
                $option = $node->name === 'create_table_option' ? (new \SqlSemantics\Platform\MySql\Lowering\TableDefinition\TableOptionRule($lowering))->option($node) : (new \SqlSemantics\Platform\MySql\Lowering\TableChange\Partition\OptionRule($lowering))->option($node);
                $name = $option instanceof EngineOption ? $option->engine : ($option instanceof \SqlSemantics\Platform\MySql\Statement\Partition\PartitionOption && $option->kind === \SqlSemantics\Platform\MySql\Statement\Partition\PartitionOptionKind::Engine ? $option->name : null);
                if ($name !== null && ServerCatalog::shared()->engine($name->value) === null) {
                    $errors[$offset] = SchemaError::UnknownStorageEngine->error($name->value);
                }
            });
        } catch (SourceException) {
            // Modern contextualization requires a complete input; legacy lookups precede later syntax errors.
            if (!$session->settings()->legacy()) {
                return [];
            }
        }
        ksort($errors);
        return $errors;
    }

    /**
     * Interleaves resolved engine warnings or stops at the first non-substitutable engine.
     *
     * @template T of object
     * @param list<array{int, T}> $notices Earlier semantic notices
     * @param array<int, SqlError> $engines Completed engine conditions
     * @return array{list<array{int, T|SqlError}>, SqlError}
     */
    public function reached(array $notices, array $engines, SqlError $fallback, bool $strict): array
    {
        $first = array_key_first($engines);
        if ($first !== null && $strict) {
            return [array_values(array_filter($notices, static fn (array $notice): bool => $notice[0] < $first)), $engines[$first]];
        }
        foreach ($engines as $offset => $engine) {
            $notices[] = [$offset, $engine];
        }
        return [$notices, $fallback];
    }

}
