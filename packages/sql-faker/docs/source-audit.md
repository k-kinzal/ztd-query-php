# Grammar, scanner and generation contracts

The generation pipeline is `Grammar AST → TerminalSequence → TokenRewriter →
LexemeGenerator and spacing constraints → SqlSerializer`. Rewrites use the
original production tree, including empty productions. Lexeme generators compose
through `Matching`, `Choice`, `Sequence` and exact `VersionCase` declarations.
The serializer only concatenates resolved pieces. There is no retry or repair of
completed SQL, and a missing handler or an empty applicable candidate set is an
error.

## Sources and version scope

[The source map](source-audit.json) records fixed upstream URLs, SHA-256 digests
and rule-to-file mappings for all supported releases. Each dialect rule or
definition also has source links in its PHPDoc; the definition/rewrite IDs name
the relevant scanner state, grammar production or parser routine. A historical
file mapping identifies where an implementation moved; it does not claim that
the entire files are semantically equivalent.

| Releases | Scanner and registration sources | Shared contracts and differences checked |
| --- | --- | --- |
| MySQL 5.6.51, 5.7.44 | Each tag's `sql/lex.h`, `sql/sql_lex.cc` | Legacy keyword/function tables; NUM/LONG_NUM/ULONGLONG_NUM thresholds; quote doubling and default backslash escapes; identifier/function lookahead; `@`, qualified names and comparison operators. 5.7 removes legacy `USE_MB` conditionals and changes keyword hash lookup. The generated unquoted identifier alphabet is ASCII. JSON arrows start in 5.7; both releases retain WITH CUBE. |
| MySQL 8.0.44 | That tag's same files | Modern registration macros and parser selector tokens. Numeric classification and quoted-string contracts agree with the declared common domains. Dollar quoting is absent. |
| MySQL 8.1.0, 8.2.0, 8.3.0, 8.4.7, 9.0.1, 9.1.0 | Each tag's same files | The dollar-quoted state and delimiter reader are present and identical across these six versions. Primary character-set lookup changes in 8.1 and 9.1; common ASCII identifier rules do not depend on its internal API. Default-mode NOT and pipes remapping remains applicable. |
| PostgreSQL 17.2 | `src/include/parser/kwlist.h`, `src/backend/parser/scan.l`, `parser.c` | Standard strings, E/Unicode/dollar strings, signed-32-bit ICONST classification, operator exceptions and lookahead tokens. `gram.y` and parse-analysis routines supply structural constraints. |
| SQLite 3.47.2 | `tool/mkkeywordhash.c`, `src/tokenize.c` | Default keyword masks, quoted names, parameters, numeric/blob forms and contextual window keywords. `parse.y`, `build.c`, `select.c`, `expr.c`, `window.c` and `sqliteLimit.h` supply structural and build-limit constraints. |

All eleven keyword profiles were recompiled from their exact upstream tables and
compared with the checked-in resources. Compilers consume complete registration
regions and fail on unknown declarations, including adjacent C string literals;
they do not silently produce a partial profile. Scanner bodies are not
transpiled from C. Their semantics are implemented manually by the declarations
and rules above, with version cases for actual differences.

MySQL structural rules also reference `sql_yacc.yy`, `create_field.cc`,
`parse_tree_*.cc`, `sql_lex.cc`, `table.cc` and `window.cc`. In 5.6/5.7,
`Create_field::init` lives in `field.cc`; older partition checks live in
`sql_partition.cc`. Rules for a production absent from a release do not create
that production. PostgreSQL grammar actions rejecting combinations of otherwise
valid tokens are handled before lexical generation: JSON_TABLE paths, schema
options, constraint capabilities, foreign-key actions, trigger/view options and
aggregate argument modes are examples. A source map and passing tests do not
establish that every semantic restriction of a DBMS has been implemented.

Additional scanner and grammar-action contracts cover hostname tokens only after
a single `@`, quoted identifier length and trailing spaces, PostgreSQL role names,
partition strategies, JSON encodings, FLOAT precision and positive column positions.
MySQL introduced string/hex/bit literals use a composed domain valid for every
declared introducer, including one fixed by a partial plan. Their constructive
values encode ASCII bytes; ordinary text still explores UTF-8 and ordinary binary
literals still explore all byte values. Explicit values keep their lexical domain
and incompatible introducers are filtered against the actual literal bytes.

## Configuration and runtime verification

MySQL rules target the release's default SQL mode, without `ANSI_QUOTES`, `IGNORE_SPACE`,
`NO_BACKSLASH_ESCAPES`, `HIGH_NOT_PRECEDENCE` or `PIPES_AS_CONCAT`.
`OR_OR_SYM` concatenation productions become `CONCAT(left, right)` so the
original operand trees and precedence survive the default scanner's pipes
remapping. NOT alternatives are normalized according to their grammar context.
PostgreSQL assumes UTF-8 and `standard_conforming_strings=on`. SQLite assumes the
default keyword set and limits for the selected release. Other modes, character
sets and custom builds require separate definitions and validation.

Source comparison, PHP unit tests, independent lexical checks and live database
checks are different evidence. The disposable native campaigns currently target
MySQL **8.4.7**, PostgreSQL **17.2** and SQLite **3.47.2**. They do not establish
live-parser coverage for the other eight MySQL releases. Preparation can also
stop at a missing relation or an unsupported command, so reaching a grammar
production or returning SQL must never be reported as parser acceptance.

## Candidate selection and exploration bounds

Large value sets use constructive domains. Integer sampling works on decimal
prefixes, including the unsigned 64-bit maximum, without machine-integer
conversion or enumeration. Character domains choose complete encoded atoms;
product domains compose chosen values rather than enumerating a Cartesian
product. Dollar delimiters are chosen once and reused. A bounded choice callback
is supplied by the plan compiler, and its selected values and candidate semantics
are frozen into the plan. Leaf generators never read fuzz bytes.

The value explorer uses bounded subsets: default strings contain at most 255
atoms, decimal fractional parts at most 30 digits, and custom operator bodies a
bounded alphabet. Overflow integers retain their scanner token family: MySQL
DECIMAL_NUM starts above the unsigned 64-bit range for integer spellings and
PostgreSQL FCONST above the signed 32-bit positive range. Constructive overflow
sampling is bounded to 65 significant digits; valid explicit magnitudes have no
artificial upper bound. This is not exhaustive coverage of every scanner spelling,
Unicode character or database size limit. Fixed representatives remain available
alongside constructive sampling, and explicit caller requests are validated by
the applicable domain. Domains never claim to be empty because a finite sample
failed.

Structural candidates remain finite and lazily enumerated, deduplicated and
counted before selection. Their cost is proportional to structural alternatives;
this interface is not a constant-time sampler for an arbitrary plugin's enormous
structural Cartesian product. Keep large variation in value domains.

Before committing a reverse candidate with an explicit pending left boundary,
`BoundaryCompletion` witnesses a compatible prefix, including intervening empty
markers and planned spelling/candidate constraints. Boundary witnesses and final
realization share the same memoized sampled values. It stops when that explicit
obligation is discharged. This contract covers local lexical boundaries; it is
not a solver for arbitrary nonlocal dependencies introduced by a custom plugin.
Such a dependency needs an explicit structural or domain rule.

Rewrite traces retain each operation's removed and inserted occurrences, chosen
version-case provenance and reasons for rejected candidates even if generation
ultimately succeeds. This preserves the distinction between the original
production, the rewritten terminal and the emitted lexeme.
