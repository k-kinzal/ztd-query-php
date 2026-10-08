<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Text;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;

/**
 * The functions of compressed strings, COMPRESS, UNCOMPRESS and UNCOMPRESSED_LENGTH, and LOAD_FILE.
 *
 * COMPRESS writes the length of the string in four bytes, low byte first, then its zlib stream,
 * and a period when that ends with a space; an empty string stays empty. UNCOMPRESSED_LENGTH
 * reads the low 30 bits of that length, 0 for a string of four bytes or fewer. UNCOMPRESS
 * inflates into a buffer of that length: a length above max_allowed_packet warns
 * (ER_TOO_BIG_FOR_UNCOMPRESS), a stream that does not fit the buffer warns ER_ZLIB_Z_BUF_ERROR and
 * any other bad stream ER_ZLIB_Z_DATA_ERROR, each answering NULL; bytes after the stream are
 * ignored. LOAD_FILE reads no file: the emulator has no server files, as a server whose
 * secure_file_priv names a directory reads none outside it, and answers NULL (verified on a
 * live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/encryption-functions.html#function_compress,
 * https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_load-file.
 *
 * @visibility MySqlMemory
 */
final class Compression
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('COMPRESS', 1, 1, fn (Frame $f, array $a): ?string => ($t = $this->bytes($f, $a[0])) === null ? null : $this->compress($t)),
            new Routine('UNCOMPRESS', 1, 1, fn (Frame $f, array $a): ?string => ($t = $this->bytes($f, $a[0])) === null ? null : $this->uncompress($f, $t)),
            new Routine('UNCOMPRESSED_LENGTH', 1, 1, fn (Frame $f, array $a): ?int => ($t = $this->bytes($f, $a[0])) === null ? null : $this->length($t)),
            new Routine('LOAD_FILE', 1, 1, static fn (Frame $f, array $a): ?string => null),
        ];
    }

    /**
     * Reads the bytes of the text of an argument, or answers null.
     */
    public function bytes(Frame $frame, Evaluable $argument): ?string
    {
        return Convert::toText($argument->evaluate($frame), $argument->domain());
    }

    /**
     * COMPRESS: the length and the zlib stream of the bytes, with a period after a trailing space.
     */
    public function compress(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }
        $compressed = pack('V', strlen($bytes)) . gzcompress($bytes);

        return str_ends_with($compressed, ' ') ? $compressed . '.' : $compressed;
    }

    /**
     * UNCOMPRESSED_LENGTH: the length COMPRESS wrote, without its two highest bits.
     */
    public function length(string $bytes): int
    {
        return strlen($bytes) <= 4 ? 0 : ord($bytes[0]) | ord($bytes[1]) << 8 | ord($bytes[2]) << 16 | (ord($bytes[3]) & 0x3F) << 24;
    }

    /**
     * UNCOMPRESS: the bytes the stream inflates to, or NULL with a warning when it does not inflate into its length.
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public function uncompress(Frame $frame, string $bytes): ?string
    {
        if ($bytes === '') {
            return '';
        }
        if (strlen($bytes) <= 4) {
            $frame->context->warning(DataError::ZlibDataError);

            return null;
        }
        $length = $this->length($bytes);
        $limit = (int) $frame->context->variables->read('max_allowed_packet');
        if ($length > $limit) {
            $frame->context->warning(DataError::UncompressedTooBig, $limit);

            return null;
        }
        $inflater = inflate_init(ZLIB_ENCODING_DEFLATE);
        set_error_handler(static fn (): bool => true);
        try {
            $inflated = $inflater === false ? false : inflate_add($inflater, substr($bytes, 4), ZLIB_FINISH);
        } finally {
            restore_error_handler();
        }
        $complete = $inflater !== false && $inflated !== false && inflate_get_status($inflater) === ZLIB_STREAM_END;
        if ($inflated !== false && ($complete ? strlen($inflated) <= $length : strlen($inflated) < $length)) {
            if ($complete) {
                return $inflated;
            }
            $frame->context->warning(DataError::ZlibDataError);

            return null;
        }
        $frame->context->warning($inflated !== false && $length > 0 ? DataError::ZlibBufferError : DataError::ZlibDataError);

        return null;
    }
}
