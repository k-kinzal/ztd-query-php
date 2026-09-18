# Conformance with Lemon

`php conformance/run.php --lemon=PATH DIRECTORY|FILE...` (or `composer conformance -- ...`) compares this package with Lemon on every `.y` file found, under each set of defines its `.defines` file lists (one set per line, names separated by spaces; an empty line is the set with no defines).

For each file and set of defines:

1. The preprocessed text must equal `lemon -E` byte for byte.
2. Lemon reads the file and writes its report: the reprint of the grammar with its symbol numbering (`-g`), the header with the token numbers, and the parser report with every state, action and precedence (`.out`). A file Lemon stops reading must raise a `SyntaxException` here; a file Lemon reads but refuses for its meaning (an empty grammar) is only read.
3. The tree is printed and Lemon reads the reprint under the same file name. The two reports must be identical.

A difference in any of the three is a failure and the exit status is 1.

## Corpus

- `corpus/`: hand-written grammars that use every declaration and every rule form Lemon accepts, a preprocessor grammar read under six sets of defines, and under `corpus/reject/` files Lemon refuses.
- `corpus.txt`: SQLite's grammars fetched by URL with `php conformance/fetch.php DIRECTORY`, which also copies the `.defines` files under `defines/` next to them.

## What the reference is

The CI job builds Lemon from `tool/lemon.c` at SQLite's `version-3.47.2` tag (SHA-256 `3661d01cb826d443a0148d93e440d3891d27782d0c3c0656e187968febb49bc0`, checked before the build), the file SQLite itself is built with at that release. Lemon has no specification apart from this source, which is why the reference is that file, unmodified, and not a re-implementation.

## Running locally

Lemon is `tool/lemon.c` in SQLite's repository, public domain, built with a C compiler:

```sh
curl -sSL -o lemon.c https://raw.githubusercontent.com/sqlite/sqlite/refs/tags/version-3.47.2/tool/lemon.c && cc -O1 -o lemon lemon.c
composer conformance -- --lemon=./lemon conformance/corpus
php conformance/fetch.php build/conformance/remote && composer conformance -- --lemon=./lemon build/conformance/remote
```
