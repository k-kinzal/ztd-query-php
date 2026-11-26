"""Independent finite and seeded models for a single native ALTER TABLE index removal."""

from itertools import product
from random import Random

import mysql_drop_index_cases as removal
from mysql_index_policy_cases import refusal
from mysql_innodb_declaration_width_cases import OLD, SCHEMA, quote

ROLES = ("primary", "clustered-unique", "unique", "ordinary")
FORMS = {"drop-index": ROLES, "drop-key": ROLES, "drop-primary-key": ("primary",)}
DIRECTIVES = {
    "no-options": (("action",), ("DEFAULT", "DEFAULT")),
    "action-before-options": (("action", "ALGORITHM=INPLACE", "LOCK=NONE"), ("INPLACE", "NONE")),
    "action-after-options": (("LOCK EXCLUSIVE", "ALGORITHM COPY", "action"), ("COPY", "EXCLUSIVE")),
    "action-between-options": (("ALGORITHM=COPY", "action", "LOCK=NONE"), ("COPY", "NONE")),
    "last-algorithm-wins": (("ALGORITHM=INPLACE", "action", "LOCK=SHARED", "ALGORITHM=COPY"), ("COPY", "SHARED")),
    "last-lock-wins": (("LOCK=NONE", "action", "ALGORITHM=COPY", "LOCK=SHARED"), ("COPY", "SHARED")),
    "recognized-overridden-instant": (("ALGORITHM=INSTANT", "action", "ALGORITHM=DEFAULT"), ("DEFAULT", "DEFAULT")),
}
DIRECT_POLICIES = ("no-options", "action-before-options", "last-algorithm-wins")
QUALIFIERS = ("unqualified", "actual-table", "actual-database-and-table", "foreign-table", "foreign-database")
LEXICAL = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\AnalysisException"
SEMANTIC = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\SemanticException"
INVALID = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\InvalidConstruction"
GRAMMAR_ERROR = ["db-error", "42000", 1064]
UNKNOWN_ALGORITHM = ["db-error", "HY000", 1800]
LEGACY_IGNORE = "mysql-5.6.51"
IGNORED_WARNING = ["Warning", 1681, "'IGNORE' is deprecated and will be removed in a future release."]


def coordinate(point, tag, rng=None):
    group, form, life, role, directive, qualification, ignore = point
    syntax, effective = DIRECTIVES[directive]
    kind = "unique" if role == "clustered-unique" else role
    origin = "table" if role == "primary" else "explicit"
    spelling, owner = "permitted-case-variant", "explicit"
    offsets = {}
    if rng is not None:
        owner = rng.choice(removal.QUALIFIERS)
        if kind != "primary":
            origin = rng.choice(("explicit", "automatic-in-order", "automatic-suffixed"))
        offsets = {"field": rng.choice(("Entité", "No", "Poj", "Σлот")) + str(rng.randrange(2000)),
                   "label": f"résult {rng.randrange(10**8)}", "offset": rng.randrange(40)}
    model = removal.definition(tag, (life, kind, origin, spelling, owner), nullable=role == "unique", **offsets)
    model["mysqlDropIndex"].update(policy=list(effective), ignore=ignore)
    item = {"point": list(point), "model": model, "syntax": syntax, "nativeError": None}
    item["nativeError"], item["boundary"] = admission(item)
    return item


def admission(item):
    _, _, life, role, directive, qualifier, ignore = item["point"]
    tag = item["model"]["release"]
    if ignore and tag != LEGACY_IGNORE:
        return GRAMMAR_ERROR, (LEXICAL, "Unexpected 'IGNORE'")
    if qualifier != "unqualified" and tag not in OLD:
        return GRAMMAR_ERROR, (LEXICAL, "Unexpected '.'")
    if qualifier == "foreign-table":
        return ["db-error", "42000", 1103], (INVALID, "different owning table")
    if qualifier == "foreign-database":
        return ["db-error", "42000", 1102], (INVALID, "different owning database")
    if directive == "recognized-overridden-instant" and tag in OLD:
        return UNKNOWN_ALGORITHM, (SEMANTIC, "INSTANT is not an ALGORITHM the server knows.")
    algorithm, lock = item["model"]["mysqlDropIndex"]["policy"]
    error = refusal(tag, "drop-" + role, life, algorithm, lock)
    return error, ((INVALID, "The algorithm and lock cannot remove this native InnoDB index.") if error else None)


def written_sql(item):
    _, form, _, _, _, qualifier, ignore = item["point"]
    model = item["model"]
    selected = model["written"]
    qualifier_parts = {
        "unqualified": (), "actual-table": (removal.TABLE,),
        "actual-database-and-table": (SCHEMA, removal.TABLE),
        "foreign-table": ("other_table",),
        "foreign-database": ("other_database", removal.TABLE),
    }[qualifier]
    index = ".".join(map(quote, (*qualifier_parts, selected)))
    forms = {"drop-index": "DROP INDEX " + index, "drop-key": "DROP KEY " + index,
             "drop-primary-key": "DROP PRIMARY KEY"}
    action = ", ".join(forms[form] if part == "action" else part for part in item["syntax"])
    owner = quote(removal.TABLE)
    if model["point"][-1] == "explicit":
        owner = quote(SCHEMA) + "." + owner
    return "ALTER " + ("IGNORE " if ignore else "") + "TABLE " + owner + " " + action


def output_sql(item):
    model = item["model"]
    role = model["mysqlDropIndex"]
    separator = ", " if role["ignore"] else " "
    policy = "".join(separator + f"{name}={value}" for name, value in zip(("ALGORITHM", "LOCK"), role["policy"]) if value != "DEFAULT")
    if role["ignore"]:
        owner = f"{quote(SCHEMA)}.{quote(removal.TABLE)}"
        return f"ALTER IGNORE TABLE {owner} DROP INDEX {quote(role['name'])}" + policy
    return removal.command(model) + policy


def core_points(finite):
    for form, roles in FORMS.items():
        for life, role, directive in product(finite["ownership"], roles, finite["orderedDirectives"]):
            yield "core", form, life, role, directive, "unqualified", False


def qualified_points(finite):
    for form, roles in list(FORMS.items())[:2]:
        for life, role, qualifier in product(finite["ownership"], roles, finite["qualifications"][1:]):
            yield "qualification", form, life, role, "no-options", qualifier, False


def legacy_points(finite):
    for form, roles in FORMS.items():
        for life, role, directive in product(finite["ownership"], roles, finite["ignoreDirectives"]):
            yield "legacy", form, life, role, directive, "unqualified", True


def direct_points(finite):
    for role, life, directive, ignore in product(ROLES, finite["ownership"], DIRECT_POLICIES, (False, True)):
        yield "direct", "drop-index", life, role, directive, "unqualified", ignore


def seeded(tag, finite, seed, count):
    rng = Random(seed)
    for ordinal in range(count):
        form = finite["forms"][ordinal % len(finite["forms"])]
        life = finite["ownership"][(ordinal // len(finite["forms"]) + seed) % len(finite["ownership"])]
        role = FORMS[form][ordinal // len(finite["forms"]) % len(FORMS[form])]
        candidates = [name for name, (_, pair) in DIRECTIVES.items()
                      if (tag not in OLD or name != "recognized-overridden-instant")
                      and refusal(tag, "drop-" + role, life, *pair) is None]
        directive = rng.choice(candidates) if ordinal > 2 else "last-algorithm-wins"
        qualifier = rng.choice(QUALIFIERS[:3]) if tag in OLD and form != "drop-primary-key" else "unqualified"
        point = "seed", form, life, role, directive, qualifier, tag == LEGACY_IGNORE and ordinal % 3 == 0
        yield coordinate(point, tag, rng=rng)


def expected(item):
    observed = removal.prediction(item["model"], item["nativeError"])
    if item["nativeError"] and item["nativeError"][2] in (1064, 1102, 1103, 1800):
        observed.update(marker=0, active=True)
    return observed
