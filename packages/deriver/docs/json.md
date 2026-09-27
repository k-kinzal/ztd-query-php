# JSON format and confidential inputs

`DerivationResult::toJson()` emits schema version `1`. Values are stored once in a graph and referenced by IDs from normal outcomes, exceptional outcomes, residuals, and query entry arguments. The schema is [result-v1.json](../resources/schema/result-v1.json).

## When the schema is used

The schema describes serialized **analysis results**, not application input or a database. `Analyzer::open()` and `AnalysisSession::derive()` do not load it. PHP callers can inspect `DerivationResult` and its values directly without any schema validator.

Use `resources/schema/result-v1.json` when another tool consumes a saved JSON report and needs to validate its fields, enumerations, and record shapes. The development-only `opis/json-schema` dependency checks emitted reports against this file in the serialization and schema contract tests. The runtime package does not depend on that validator.

`ValueReader` separately checks value references, graph limits, and lossless scalar encodings in PHP. JSON Schema describes the record structure; it does not establish every cross-record reference or prove the correctness of an analysis.

## Lossless scalars

| Scalar tag | Payload |
| --- | --- |
| `bytes` | Base64 of the original PHP string bytes |
| `int64` | Signed decimal text |
| `float64` | Hexadecimal IEEE 754 binary64 in network byte order |
| `bool` | JSON Boolean |
| `null` | JSON null |

Floats retain negative zero, infinities, and NaN bit patterns. Array operands are ordered key/value records, preserving integer keys, string keys, and insertion order. Shared graph references avoid duplicating identical immutable terms.

Metadata is UTF-8 text when possible. Invalid UTF-8 and text beginning with the reserved prefix `~b64~` are encoded as that prefix followed by Base64 of the original bytes. This rule applies to metadata mapping keys as well as values. `JsonText::decode()` recovers the original bytes. ValueGraph's tagged scalar payloads use their own encoding described above.

## Reading value graphs

`ValueReader::fromJson($json)` validates and reconstructs the value table. `read($id)` returns an interned `Term`: repeated references retain shared object identity. The reader rejects dangling references, cycles, duplicate keys, noncanonical scalar encodings, integers outside the signed 64-bit range, and graph depth or size violations.

The default limits are 20,000 value records, a depth of 128, and 32 MiB of JSON input. Node and depth limits can be lowered with named arguments. The reader validates every value record, including records that no outcome references. Validate the rest of the report against the packaged JSON Schema before consuming query, assessment, or evidence fields.

```php
$reader = \Deriver\Result\Serialization\ValueReader::fromJson($result->toJson());
$value = $reader->read('v0');
```

Each record carries its own `secret` flag. Confidentiality also propagates through child references. Reading a redacted record produces a confidential opaque term with reason `REDACTED`; it never reconstructs the missing payload as a known null. Unredacted records preserve exact scalar bits, ordered keys, metadata bytes, and explicit secrecy flags.

## Confidentiality

Mark explicit confidential inputs with `Term::constant($value, secret: true)` or `Term::fromNative($value, secret: true)`. Derived expressions retain confidentiality through their operands. JSON output redacts confidential values by default, including values supplied in entry queries. Value and state projections preserve explicit confidentiality on the selected containers, reference handles, object handles, and abstract slots. A confidential sibling field does not make a separately selected public field confidential.

`toJson(includeSecrets: true)` explicitly includes their lossless payloads. Keep this output within the intended trust boundary.

Confidential expression and query fingerprints use HMAC-SHA-256 with a process-local random key. The key is never exported. This prevents a report's identifiers from serving as plain hashes of low-entropy secrets. Fingerprints involving confidential inputs are stable within the process; they are not portable cache keys across processes.

Environment variables are symbolic unless provided explicitly through configuration or a provider. Analysis never reads the host environment to resolve an application's `getenv()` result.

## Storage graphs

Each outcome contains `storage.bindings` and `storage.cells`, whose values reference the shared value table. `exceptionalOutcomes` also contains its derivation `evidence` list. Location, cell, and object terms retain storage identities; a PHP object cycle is a cycle through these identity links, not a cycle in the immutable value table. `ValueReader` can therefore decode all term records without recursively expanding the heap.

Confidential object or reference handles propagate their label to reachable storage records. Those records are redacted by default along with ordinary secret values. Pass `includeSecrets: true` only when the output destination is authorized to receive the captured input.
