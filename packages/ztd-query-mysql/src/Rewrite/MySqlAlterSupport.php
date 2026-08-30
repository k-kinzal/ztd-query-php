<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Components\OptionsArray;
use ZtdQuery\Platform\MySql\Dialect\MySqlStatementOptions;

/**
 * Which ALTER TABLE operations ZTD will not pretend to have carried out.
 *
 * The shadow holds rows and what declares them, and nothing else. An index,
 * a constraint, a storage engine, a partitioning scheme or a column default
 * is not something it can hold, so a statement asking for one of those would
 * appear to have been simulated while having done nothing. Refusing is the
 * only honest answer, and what to refuse is written out here as data rather
 * than as a run of conditions.
 */
final class MySqlAlterSupport
{
    /** @var array<string, list<string>> Keyword => the keywords it may not be written with */
    private const REFUSED_TOGETHER = [
        'ADD' => ['INDEX', 'KEY', 'FULLTEXT', 'SPATIAL', 'UNIQUE', 'CONSTRAINT'],
        'DROP' => ['INDEX', 'KEY', 'CONSTRAINT'],
        'RENAME' => ['INDEX', 'KEY'],
        'ALTER' => ['SET DEFAULT', 'DROP DEFAULT'],
    ];

    /** @var list<string> Keywords that name something the shadow cannot hold on their own */
    private const REFUSED_ALONE = [
        'ORDER', 'ORDER BY', 'CONVERT', 'ENGINE',
        'PARTITION', 'ADD PARTITION', 'DROP PARTITION', 'TRUNCATE PARTITION',
        'COALESCE PARTITION', 'REORGANIZE PARTITION', 'EXCHANGE PARTITION',
        'ANALYZE PARTITION', 'CHECK PARTITION', 'OPTIMIZE PARTITION',
        'REBUILD PARTITION', 'REPAIR PARTITION', 'REMOVE PARTITIONING',
    ];

    /** @var list<string> Words the parser did not take that name a column default */
    private const REFUSED_DEFAULT_WORDS = ['SET', 'DROP'];

    /** @var list<string> Words the parser did not take that name a row order */
    private const REFUSED_ORDER_WORDS = ['ORDER BY', 'ORDER'];

    /** @var list<string> What a word the parser did not take may be part of */
    private const REFUSED_WITHIN = ['PARTITION', 'ENGINE', 'SPATIAL', 'FULLTEXT'];

    /**
     * @param MySqlStatementOptions $options Reports whether a keyword was written
     */
    public function __construct(private readonly MySqlStatementOptions $options = new MySqlStatementOptions())
    {
    }

    /**
     * Reports whether the statement as written asks for something the shadow cannot hold.
     *
     * The parser does not always take a column default or a row order as an
     * operation of its own, so the statement itself is read for them.
     *
     * @param string $sql The statement, as written
     *
     * @return bool True when it does
     */
    public function refusesStatement(string $sql): bool
    {
        $written = strtoupper($sql);
        foreach (['SET DEFAULT', 'DROP DEFAULT', 'ORDER BY'] as $refused) {
            if (str_contains($written, $refused)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether one operation asks for something the shadow cannot hold.
     *
     * @param AlterOperation $operation The operation, as the parser reads it
     *
     * @return bool True when it does
     */
    public function refusesOperation(AlterOperation $operation): bool
    {
        $options = $operation->options;
        if ($options->isEmpty()) {
            return false;
        }
        foreach (self::REFUSED_TOGETHER as $keyword => $refused) {
            if ($this->options->isSet($options, $keyword) && $this->anySet($options, $refused)) {
                return true;
            }
        }
        if ($this->anySet($options, self::REFUSED_ALONE)) {
            return true;
        }
        if ($this->options->isSet($options, 'ALTER') && $this->saysAnyOf($operation, self::REFUSED_DEFAULT_WORDS)) {
            return true;
        }
        if ($this->saysAnyOf($operation, self::REFUSED_ORDER_WORDS)) {
            return true;
        }

        return $this->saysAnythingWithin($operation, self::REFUSED_WITHIN);
    }

    /**
     * Reports whether any of the keywords was written.
     *
     * @param OptionsArray $options The keywords the parser took
     * @param list<string> $keywords Keywords to look for
     *
     * @return bool True when any was
     */
    public function anySet(OptionsArray $options, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($this->options->isSet($options, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether the operation carries one of these words the parser did not take.
     *
     * @param AlterOperation $operation The operation, as the parser reads it
     * @param list<string> $words Words to look for
     *
     * @return bool True when it carries one
     */
    public function saysAnyOf(AlterOperation $operation, array $words): bool
    {
        foreach ($this->unknownWords($operation) as $word) {
            if (in_array($word, $words, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether any word the parser did not take is written around one of these.
     *
     * @param AlterOperation $operation The operation, as the parser reads it
     * @param list<string> $needles What a word may be part of
     *
     * @return bool True when one is
     */
    public function saysAnythingWithin(AlterOperation $operation, array $needles): bool
    {
        foreach ($this->unknownWords($operation) as $word) {
            foreach ($needles as $needle) {
                if (str_contains($word, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Answers the words of the operation the parser did not take, as written.
     *
     * @param AlterOperation $operation The operation, as the parser reads it
     *
     * @return list<string> The words, upper-cased
     */
    public function unknownWords(AlterOperation $operation): array
    {
        $words = [];
        foreach (is_array($operation->unknown) ? $operation->unknown : [] as $token) {
            $words[] = strtoupper(is_string($token->value) ? $token->value : '');
        }

        return $words;
    }
}
