<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Prepared;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Catalog;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Statement\Snapshot;

/**
 * One lexical interpretation available when a MySQL preparation is invalidated.
 *
 * The five lexer switches form 32 combinations. Client character sets come from
 * the selected release's catalog; UCS-2, UTF-16 and UTF-32 cannot be clients.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-connection.html.
 *
 * @visibility public
 * @example Identifying a lexical interpretation
 *     $language = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847, new \SqlSemantics\Contract\LexicalSettings('ANSI_QUOTES'));
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation($language, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4')))->key() // => '10000:utf8mb4'
 * @example Rejecting an impermissible client encoding
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf16')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Rejecting an encoding missing from the release
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('gb18030')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Interpretation
{
    use Snapshot;

    /**
     * Rejects a profile outside the server's preparation input domain.
     */
    public function __construct(public readonly LanguageProfile $language, public readonly Charset $client)
    {
        Check::input($language->grammar->database() === 'mysql', 'A MySQL preparation uses a MySQL grammar.');
        Check::input(isset(Catalog::shared()->defaults[$language->grammar->value][$client->name]) && $client->minLength() === 1, 'A preparation uses a client character set available in its release.');
        Check::input(Charset::known($client->name)->maxLength === $client->maxLength, 'A client character set has its catalog width.');
    }

    /**
     * Identifies the effective switches and encoding within one release and protocol.
     */
    public function key(): string
    {
        $flags = $this->language->lexical;
        return (int) $flags->ansiQuotes . (int) $flags->pipesAsConcat . (int) $flags->highNotPrecedence . (int) $flags->noBackslashEscapes . (int) $flags->ignoreSpace . ':' . $this->client->name;
    }

    /**
     * Enumerates the complete input domain without sampling or active-mode shortcuts.
     *
     * @return non-empty-list<self>
     */
    public static function all(GrammarRelease $release, ParameterStyle $parameters): array
    {
        Check::input($release->database() === 'mysql', 'A MySQL preparation uses a MySQL grammar.');
        $interpretations = [];
        $names = ['ANSI_QUOTES', 'PIPES_AS_CONCAT', 'HIGH_NOT_PRECEDENCE', 'NO_BACKSLASH_ESCAPES', 'IGNORE_SPACE'];
        for ($mask = 0; $mask < 32; $mask++) {
            $flags = [];
            foreach ($names as $bit => $name) {
                if (($mask & (1 << $bit)) !== 0) {
                    $flags[] = $name;
                }
            }
            $language = new LanguageProfile($release, new LexicalSettings(implode(',', $flags)), $parameters);
            foreach (Catalog::shared()->defaults[$release->value] as $name => $_) {
                $client = Charset::known($name);
                if ($client->minLength() === 1) {
                    $interpretations[] = new self($language, $client);
                }
            }
        }
        Check::invariant($interpretations !== [], 'Every MySQL release has client character sets.');
        return $interpretations;
    }
}
