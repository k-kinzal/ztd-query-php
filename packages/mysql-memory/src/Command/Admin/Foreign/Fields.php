<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin\Foreign;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Session\Session;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Stores foreign-server options in their mysql.servers character fields.
 *
 * Text is stored in utf8mb3, with 64-character bounds and CHAR space removal.
 * From MySQL 8.0, Host instead holds 255 ASCII characters. Its first unconvertible
 * character warns; text beyond the field bound is discarded before conversion.
 * Observed through CREATE, ALTER and SELECT on five supported MySQL releases.
 *
 * @visibility MySqlMemory
 */
final class Fields
{
    /**
     * Converts the decoded options after the command has checked the server name.
     *
     * @param array<string, string|int> $options The effective input options
     * @return array<string, string|int>
     */
    public function options(array $options, Session $session): array
    {
        $stored = [];
        foreach ($options as $name => $value) {
            $host = $name === 'host' && !$session->settings()->legacy();
            $stored[$name] = is_string($value) ? $this->text($value, $session, $host ? 255 : 64, $host) : $value;
        }

        return $stored;
    }

    /**
     * Decodes the values held in the server-name cache before field storage.
     *
     * @param array<string, string|int> $options The input options
     * @return array<string, string|int>
     */
    public function cached(array $options, Session $session): array
    {
        $source = Charset::named((string) $session->variables->read('character_set_client')) ?? Charset::known('utf8mb4');
        return array_map(static fn (string|int $value): string|int => is_string($value) ? Encoding::convert($value, $source, Charset::known('utf8mb3')) : $value, $options);
    }

    /**
     * Keeps input options whose decoded values differ from the cached definition.
     *
     * @param array<string, string|int> $options The input options
     * @param array<string, string|int> $cached The definition cached by creation or the previous ALTER
     * @return array<string, string|int>
     */
    public function changed(array $options, array $cached, Session $session): array
    {
        $decoded = $this->cached($options, $session);
        return array_filter($options, static fn (string $name): bool => $decoded[$name] !== ($cached[$name] ?? (is_string($decoded[$name]) ? '' : 0)), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Stores an already decoded wrapper name without decoding client bytes twice.
     */
    public function wrapper(string $decoded, Session $session): string
    {
        $text = Encoding::convert($decoded, Charset::known('utf8mb4'), Charset::known('utf8mb3'));
        $text = $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? explode("\0", $text, 2)[0] : $text;

        return rtrim(mb_substr($text, 0, 64, 'UTF-8'), ' ');
    }

    /**
     * Converts text from the connection's input character set to a stored field.
     */
    public function text(string $value, Session $session, int $length = 64, bool $ascii = false): string
    {
        $source = Charset::named((string) $session->variables->read('character_set_client')) ?? Charset::known('utf8mb4');
        $converted = Encoding::convert($value, $source, Charset::known('utf8mb3'));
        $converted = $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? explode("\0", $converted, 2)[0] : $converted;
        $text = mb_substr($converted, 0, $length, 'UTF-8');
        if ($ascii && preg_match('/[\x80-\xFF]/', $text, $match, PREG_OFFSET_CAPTURE) === 1) {
            $rest = substr($converted, $match[0][1]);
            $quoted = (string) preg_replace_callback('/[^\x20-\x7E]/', static fn (array $byte): string => sprintf('\\x%02X', ord($byte[0])), substr($rest, 0, 6));
            $session->diagnostics->warning(DataError::TruncatedWrongValueForField, DataError::TruncatedWrongValueForField->message('string', $quoted . (strlen($rest) > 6 ? '...' : ''), 'Host', 1));
        }

        return rtrim($ascii ? Encoding::convert($text, Charset::known('utf8mb3'), Charset::known('ascii')) : $text, ' ');
    }
}
