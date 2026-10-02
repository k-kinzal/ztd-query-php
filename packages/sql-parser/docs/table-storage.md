# Parse-table storage

Shipped tables contain the existing `SQLPTBL1` codec bytes directly. Loading and
saving these tables requires no zlib extension. Composer/package distribution may
still compress an archive; that is separate from the installed PHP runtime.

The [migration manifest](../resources/table-storage-migration.json) records the old
DEFLATE-container digest and the digest of its complete decoded payload for each
of the twelve releases. Each new `.bin` file is exactly that decoded payload. No
production, symbol, action, fallback, default, or supported release was removed or
changed. SQL Semantics pins the new file digests in its language profiles. The
uncompressed tables occupy approximately 24 MB instead of 1.3 MB on disk.

`TableFile::save()` writes this format, so rebuilding from the original grammars
uses the same representation. `TableFile::load()` caches by path and actual file
contents; replacing a file cannot silently reuse an older grammar at that path.

Old custom compressed table files remain readable when the optional zlib extension
is available. Regenerate them with `TableFile::save()` to use the extension-free
format. Shipped profiles never depend on the legacy decompression path.
