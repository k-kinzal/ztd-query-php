"""Read the frozen production lists as data and expose unresolved semantic classification."""

import hashlib
import json
import re

from scope import CONTRACTS, TOOL

BASELINE = "df59b1f5e920748a19a5bca3df5e93dfa38bdbafcad6617a464c5b533624a898"
RULE = re.compile(r"^    '([A-Za-z_][A-Za-z0-9_]*)' => \[$")
ALTERNATIVE = re.compile(r"^        '([^'\\]*)',$")
SQLITE = "packages/sql-semantics-sqlite/resources/productions/sqlite-3.47.2.php"
RELATED_SQLITE_DOMAINS = {
    "term: NULL|FLOAT|BLOB": ("sqlite-scalars-v1.json",),
    "term: STRING": ("sqlite-scalars-v1.json",),
    "term: INTEGER": ("sqlite-scalars-v1.json",),
    "expr: expr AND expr": ("sqlite-scalars-v1.json",),
    "expr: expr OR expr": ("sqlite-scalars-v1.json",),
    "expr: expr LT|GT|GE|LE expr": ("sqlite-scalars-v1.json",),
    "expr: expr EQ|NE expr": ("sqlite-scalars-v1.json",),
    "expr: expr IS expr": ("sqlite-scalars-v1.json",),
    "expr: expr IS NOT expr": ("sqlite-scalars-v1.json",),
    "cmd: select": ("contract-v1.json", "sqlite-relational-v1.json", "sqlite-result-v1.json",
                    "sqlite-values-v1.json", "sqlite-local-source-v1.json"),
    "cmd: with DELETE FROM xfullname indexed_opt where_opt_ret": ("sqlite-delete-v1.json",),
    "cmd: with insert_cmd INTO xfullname idlist_opt select upsert": ("sqlite-insert-v1.json",),
    "cmd: with UPDATE orconf xfullname indexed_opt SET setlist from where_opt_ret": ("sqlite-update-v1.json",),
    "cmd: DROP TABLE ifexists fullname": ("sqlite-drop-table-v1.json",),
    "cmd: create_table create_table_args": ("sqlite-create-table-v1.json",),
}


def parse(root, path, expected):
    file = (root / path).read_bytes()
    if hashlib.sha256(file).hexdigest() != expected["sha256"]:
        raise ValueError(f"Grammar artifact differs from the required baseline: {path}")
    lines = iter(file.decode().splitlines())
    if not any(line == "return [" for line in lines):
        raise ValueError(f"Missing grammar list in {path}")
    rules = {}
    current = None
    end = False
    for line in lines:
        if current is None:
            if line == "];":
                end = True
                break
            match = RULE.fullmatch(line)
            if match is None or match[1] in rules:
                raise ValueError(f"Unexpected grammar rule in {path}: {line}")
            current = match[1]
            rules[current] = []
        elif line == "    ]," and rules[current]:
            current = None
        else:
            match = ALTERNATIVE.fullmatch(line)
            if match is None or not match[1].startswith(current + ":"):
                raise ValueError(f"Unexpected grammar alternative in {path}: {line}")
            rules[current].append(match[1])
    nonempty = sum(alternative != rule + ":" for rule, alternatives in rules.items() for alternative in alternatives)
    if not end or any(lines) or nonempty != expected["alternatives"]:
        raise ValueError(f"Incomplete grammar alternatives in {path}")
    return rules


def inventory(root):
    requirements = (TOOL / "required-grammars.json").read_bytes()
    if hashlib.sha256(requirements).hexdigest() != BASELINE:
        raise ValueError("The required grammar inventory differs from the baseline revision")
    artifacts = {}
    for path, record in json.loads(requirements)["artifacts"].items():
        rules = parse(root, path, record)
        related = RELATED_SQLITE_DOMAINS if path == SQLITE else {}
        signatures = {alternative for alternatives in rules.values() for alternative in alternatives}
        if not all(alternative in signatures and all(contract in CONTRACTS for contract in contracts)
                   for alternative, contracts in related.items()):
            raise ValueError(f"Unknown production or domain in scope correspondence: {path}")
        count = sum(map(len, rules.values()))
        artifacts[path] = {
            "sha256": record["sha256"],
            "nonemptyAlternatives": record["alternatives"],
            "emptyAlternatives": count - record["alternatives"],
            "totalAlternatives": count,
            "rules": rules,
            "relatedDomains": {alternative: list(contracts) for alternative, contracts in related.items()},
        }
    return {
        "requirementsDigest": BASELINE, "artifacts": artifacts,
        "totalAlternatives": sum(record["totalAlternatives"] for record in artifacts.values()),
        "emptyAlternatives": sum(record["emptyAlternatives"] for record in artifacts.values()),
        "classification": "Each rule and alternative requires semantic classification. Related domain records exercise part of the named production family.",
    }
