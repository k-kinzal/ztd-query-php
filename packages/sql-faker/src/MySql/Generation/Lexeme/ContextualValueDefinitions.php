<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;

/**
 * Value domains checked by sql_yacc.yy semantic actions, separate from unrestricted scanner values.
 */
final class ContextualValueDefinitions
{
    /**
     * Declares exact releases; MySQL 5.7 only supports rotation of the InnoDB master key.
     */
    public function create(string $version): LexemeGenerator
    {
        return new VersionedLexemeGenerator(
            $version,
            new VersionCase(['mysql-5.7.44'], $this->domain('ROTATE_KEY_ENGINE', ['INNODB'], 'alter_instance_action'), 'mysql-5.7-key-rotation'),
            new VersionCase(
                ['mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                new ChoiceLexemeGenerator(
                    $this->domain('ROTATE_KEY_ENGINE', ['INNODB', 'BINLOG'], 'alter_instance_action'),
                    $this->domain('REDO_ENGINE', ['INNODB'], 'alter_instance_action'),
                    $this->domain('REDO_LOG_NAME', ['REDO_LOG'], 'alter_instance_action'),
                    $this->domain('LOAD_COUNT_NAME', ['COUNT'], 'opt_source_count'),
                    new IntegerLexemeGenerator('LOAD_SOURCE_COUNT', '1', '2147483647', ['1'], 'sql/sql_yacc.yy:opt_source_count'),
                    new PatternLexemeGenerator('REPLICATION_FLAG_NUMBER', "/\\A(?:0*[01]|0x0*[01]|[xX]'(?:00)*0[01]')\\z/D", ['0', '1'], 'number', 'sql/sql_yacc.yy:SOURCE_CONNECTION_AUTO_FAILOVER'),
                ),
                'mysql-8-and-9-instance-actions',
            ),
        );
    }

    /**
     * Exposes every spelling explicitly compared by the source action for a contextual domain.
     * @param non-empty-list<string> $words
     */
    public function domain(string $terminal, array $words, string $rule): LexemeGenerator
    {
        $pattern = implode('|', array_map(static fn (string $word): string => preg_quote($word, '~'), $words));
        return new PatternLexemeGenerator($terminal, '~\A(?:' . $pattern . ')\z~Di', $words, 'identifier', 'sql/sql_yacc.yy:' . $rule);
    }
}
