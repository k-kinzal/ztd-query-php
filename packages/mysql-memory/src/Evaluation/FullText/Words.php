<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\FullText;

use MySqlMemory\Typing\Ordering;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * Splits indexed text using the documented built-in full-text word boundaries.
 *
 * Stopwords were observed through INFORMATION_SCHEMA.INNODB_FT_DEFAULT_STOPWORD.
 * Source: https://dev.mysql.com/doc/refman/8.0/en/fulltext-natural-language.html.
 *
 * @visibility MySqlMemory
 */
final class Words
{
    /**
     * Default MyISAM index stopwords as published in the SQL reference manual.
     * Source: https://dev.mysql.com/doc/refman/8.0/en/fulltext-stopwords.html.
     *
     * @var list<string>
     */
    public const MYISAM_STOPWORDS = [
        'a\'s', 'able', 'about', 'above', 'according', 'accordingly', 'across', 'actually',
        'after', 'afterwards', 'again', 'against', 'ain\'t', 'all', 'allow', 'allows',
        'almost', 'alone', 'along', 'already', 'also', 'although', 'always', 'am',
        'among', 'amongst', 'an', 'and', 'another', 'any', 'anybody', 'anyhow',
        'anyone', 'anything', 'anyway', 'anyways', 'anywhere', 'apart', 'appear', 'appreciate',
        'appropriate', 'are', 'aren\'t', 'around', 'as', 'aside', 'ask', 'asking',
        'associated', 'at', 'available', 'away', 'awfully', 'be', 'became', 'because',
        'become', 'becomes', 'becoming', 'been', 'before', 'beforehand', 'behind', 'being',
        'believe', 'below', 'beside', 'besides', 'best', 'better', 'between', 'beyond',
        'both', 'brief', 'but', 'by', 'c\'mon', 'c\'s', 'came', 'can',
        'can\'t', 'cannot', 'cant', 'cause', 'causes', 'certain', 'certainly', 'changes',
        'clearly', 'co', 'com', 'come', 'comes', 'concerning', 'consequently', 'consider',
        'considering', 'contain', 'containing', 'contains', 'corresponding', 'could', 'couldn\'t', 'course',
        'currently', 'definitely', 'described', 'despite', 'did', 'didn\'t', 'different', 'do',
        'does', 'doesn\'t', 'doing', 'don\'t', 'done', 'down', 'downwards', 'during',
        'each', 'edu', 'eg', 'eight', 'either', 'else', 'elsewhere', 'enough',
        'entirely', 'especially', 'et', 'etc', 'even', 'ever', 'every', 'everybody',
        'everyone', 'everything', 'everywhere', 'ex', 'exactly', 'example', 'except', 'far',
        'few', 'fifth', 'first', 'five', 'followed', 'following', 'follows', 'for',
        'former', 'formerly', 'forth', 'four', 'from', 'further', 'furthermore', 'get',
        'gets', 'getting', 'given', 'gives', 'go', 'goes', 'going', 'gone',
        'got', 'gotten', 'greetings', 'had', 'hadn\'t', 'happens', 'hardly', 'has',
        'hasn\'t', 'have', 'haven\'t', 'having', 'he', 'he\'s', 'hello', 'help',
        'hence', 'her', 'here', 'here\'s', 'hereafter', 'hereby', 'herein', 'hereupon',
        'hers', 'herself', 'hi', 'him', 'himself', 'his', 'hither', 'hopefully',
        'how', 'howbeit', 'however', 'i\'d', 'i\'ll', 'i\'m', 'i\'ve', 'ie',
        'if', 'ignored', 'immediate', 'in', 'inasmuch', 'inc', 'indeed', 'indicate',
        'indicated', 'indicates', 'inner', 'insofar', 'instead', 'into', 'inward', 'is',
        'isn\'t', 'it', 'it\'d', 'it\'ll', 'it\'s', 'its', 'itself', 'just',
        'keep', 'keeps', 'kept', 'know', 'known', 'knows', 'last', 'lately',
        'later', 'latter', 'latterly', 'least', 'less', 'lest', 'let', 'let\'s',
        'like', 'liked', 'likely', 'little', 'look', 'looking', 'looks', 'ltd',
        'mainly', 'many', 'may', 'maybe', 'me', 'mean', 'meanwhile', 'merely',
        'might', 'more', 'moreover', 'most', 'mostly', 'much', 'must', 'my',
        'myself', 'name', 'namely', 'nd', 'near', 'nearly', 'necessary', 'need',
        'needs', 'neither', 'never', 'nevertheless', 'new', 'next', 'nine', 'no',
        'nobody', 'non', 'none', 'noone', 'nor', 'normally', 'not', 'nothing',
        'novel', 'now', 'nowhere', 'obviously', 'of', 'off', 'often', 'oh',
        'ok', 'okay', 'old', 'on', 'once', 'one', 'ones', 'only',
        'onto', 'or', 'other', 'others', 'otherwise', 'ought', 'our', 'ours',
        'ourselves', 'out', 'outside', 'over', 'overall', 'own', 'particular', 'particularly',
        'per', 'perhaps', 'placed', 'please', 'plus', 'possible', 'presumably', 'probably',
        'provides', 'que', 'quite', 'qv', 'rather', 'rd', 're', 'really',
        'reasonably', 'regarding', 'regardless', 'regards', 'relatively', 'respectively', 'right', 'said',
        'same', 'saw', 'say', 'saying', 'says', 'second', 'secondly', 'see',
        'seeing', 'seem', 'seemed', 'seeming', 'seems', 'seen', 'self', 'selves',
        'sensible', 'sent', 'serious', 'seriously', 'seven', 'several', 'shall', 'she',
        'should', 'shouldn\'t', 'since', 'six', 'so', 'some', 'somebody', 'somehow',
        'someone', 'something', 'sometime', 'sometimes', 'somewhat', 'somewhere', 'soon', 'sorry',
        'specified', 'specify', 'specifying', 'still', 'sub', 'such', 'sup', 'sure',
        't\'s', 'take', 'taken', 'tell', 'tends', 'th', 'than', 'thank',
        'thanks', 'thanx', 'that', 'that\'s', 'thats', 'the', 'their', 'theirs',
        'them', 'themselves', 'then', 'thence', 'there', 'there\'s', 'thereafter', 'thereby',
        'therefore', 'therein', 'theres', 'thereupon', 'these', 'they', 'they\'d', 'they\'ll',
        'they\'re', 'they\'ve', 'think', 'third', 'this', 'thorough', 'thoroughly', 'those',
        'though', 'three', 'through', 'throughout', 'thru', 'thus', 'to', 'together',
        'too', 'took', 'toward', 'towards', 'tried', 'tries', 'truly', 'try',
        'trying', 'twice', 'two', 'un', 'under', 'unfortunately', 'unless', 'unlikely',
        'until', 'unto', 'up', 'upon', 'us', 'use', 'used', 'useful',
        'uses', 'using', 'usually', 'value', 'various', 'very', 'via', 'viz',
        'vs', 'want', 'wants', 'was', 'wasn\'t', 'way', 'we', 'we\'d',
        'we\'ll', 'we\'re', 'we\'ve', 'welcome', 'well', 'went', 'were', 'weren\'t',
        'what', 'what\'s', 'whatever', 'when', 'whence', 'whenever', 'where', 'where\'s',
        'whereafter', 'whereas', 'whereby', 'wherein', 'whereupon', 'wherever', 'whether', 'which',
        'while', 'whither', 'who', 'who\'s', 'whoever', 'whole', 'whom', 'whose',
        'why', 'will', 'willing', 'wish', 'with', 'within', 'without', 'won\'t',
        'wonder', 'would', 'wouldn\'t', 'yes', 'yet', 'you', 'you\'d', 'you\'ll',
        'you\'re', 'you\'ve', 'your', 'yours', 'yourself', 'yourselves', 'zero',
    ];

    /**
     * @param Collation $collation The indexed column's comparison rules
     * @param int $minimum The minimum indexed word length
     * @param int $maximum The maximum indexed word length
     * @param list<string> $stopwords The index's excluded words
     */
    public function __construct(public readonly Collation $collation, public readonly int $minimum = 3, public readonly int $maximum = 84, public readonly array $stopwords = ['a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for', 'from', 'how', 'i', 'in', 'is', 'it', 'la', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'www'])
    {
    }

    /**
     * Selects each engine's default token lengths and documented stopword dictionary.
     */
    public static function forEngine(Collation $collation, bool $myIsam): self
    {
        return $myIsam ? new self($collation, 4, 84, self::MYISAM_STOPWORDS) : new self($collation);
    }

    /**
     * @return list<string> Unicode words, including words excluded from the index
     */
    public function split(string $text, Charset $charset): array
    {
        $text = Encoding::convert($text, $charset, Charset::known('utf8mb4'));
        preg_match_all("/[\\p{L}\\p{N}_]+(?:'[\\p{L}\\p{N}_]+)*/u", $text, $matches);
        return $matches[0];
    }

    /**
     * Tests index length and stopword constraints before collation weighting.
     */
    public function indexed(string $word): bool
    {
        $length = mb_strlen($word, 'UTF-8');
        return $length >= $this->minimum && $length <= $this->maximum && !in_array(mb_strtolower($word, 'UTF-8'), $this->stopwords, true);
    }

    /**
     * Returns the collation equality key of an already decoded word.
     */
    public function key(string $word): string
    {
        return Ordering::of($this->collation)->key(Encoding::convert($word, Charset::known('utf8mb4'), $this->collation->charset));
    }

    /**
     * @param list<string> $words Decoded document words in position order
     * @return array<string, int> Indexed word frequencies
     */
    public function frequencies(array $words): array
    {
        $frequencies = [];
        foreach ($words as $word) {
            if ($this->indexed($word)) {
                $key = $this->key($word);
                $frequencies[$key] = ($frequencies[$key] ?? 0) + 1;
            }
        }
        return $frequencies;
    }
}
