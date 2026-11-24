"""Independent physical index fixtures and layout rules for SQLite metadata."""
from typing import NamedTuple, Optional

from run_sqlite_create_table import quoted

LAYOUTS = {
    "separate": "a INT, b TEXT COLLATE NOCASE, c REAL, d TEXT, UNIQUE(c), PRIMARY KEY(b DESC, a)",
    "equivalent": "a INT, b TEXT COLLATE NOCASE, c REAL, d TEXT, UNIQUE(b,a), PRIMARY KEY(b DESC,a)",
    "inline": "a INTEGER PRIMARY KEY DESC, b TEXT COLLATE NOCASE UNIQUE, c REAL, d TEXT",
    "different-collation": "a INT, b TEXT COLLATE NOCASE, c REAL, d TEXT, UNIQUE(b COLLATE BINARY,a), PRIMARY KEY(b DESC,a)",
    "duplicate-primary-term": "a INT, b TEXT COLLATE NOCASE, c REAL, d TEXT, PRIMARY KEY(b DESC,a,b)",
    "unique-after": "a INT, b TEXT COLLATE NOCASE, c REAL, d TEXT, PRIMARY KEY(b DESC,a), UNIQUE(b,a)",
    "unique-inline-before": "a INT, b TEXT COLLATE NOCASE UNIQUE, c REAL, d TEXT, PRIMARY KEY(b DESC)",
}
COLUMNS = ("a", "b", "c", "d")


class Term(NamedTuple):
    slot: int
    name: Optional[str]
    descending: int = 0
    collate: str = "BINARY"


class Index(NamedTuple):
    schema: str
    owner: str
    name: str
    keys: tuple
    auxiliary: tuple
    storage: bool = False


def term(slot, descending=0, collate=None):
    return Term(slot, COLUMNS[slot], descending, collate or ("NOCASE" if slot == 1 else "BINARY"))


def keys(layout):
    pk = (term(1, 1), term(0))
    return {
        "separate": [(False, (term(2),)), (True, pk)],
        "equivalent": [(False, (term(1), term(0))), (True, pk)],
        "inline": [(True, (term(0, 1),)), (False, (term(1),))],
        "different-collation": [(False, (term(1, collate="BINARY"), term(0))), (True, pk)],
        "duplicate-primary-term": [(True, (*pk, term(1)))],
        "unique-after": [(True, pk), (False, (term(1), term(0)))],
        "unique-inline-before": [(False, (term(1),)), (True, (term(1, 1),))],
    }[layout]


def signature(terms):
    return tuple((value.slot, value.collate) for value in terms)


def physical(layout):
    installed = {}
    for primary, terms in keys(layout):
        key = signature(terms)
        state = installed.get(key)
        if state is None:
            installed[key] = (primary, terms)
        elif primary:
            installed[key] = (True, state[1])
    return list(installed.values())


def distinct_terms(terms):
    first = {}
    for value in terms:
        first.setdefault((value.slot, value.collate), value)
    return tuple(first.values())


def primary_ordinal(layout):
    return next(index for index, (primary, _) in enumerate(physical(layout), 1) if primary)


def automatic(table, ordinal):
    return f"sqlite_autoindex_{table}_{ordinal}"


def explicit_ddl(item):
    direction = "DESC" if item["descending"] else "ASC"
    yield "CREATE INDEX main.named ON stock (code COLLATE RTRIM DESC, flag)"
    yield f"CREATE INDEX main.expression_index ON stock ((flag + {item['offset']}) {direction}, code) WHERE code IS NOT NULL"
    yield "CREATE INDEX main.collision ON stock (flag, code COLLATE BINARY)"
    yield 'CREATE INDEX main."1" ON stock(flag)'
    yield 'CREATE INDEX main."+1" ON stock(code)'
    yield f"CREATE INDEX {quoted(item['attachment'])}.named ON {quoted(item['table'])} (c DESC, a, b COLLATE BINARY)"
    if item["temp"]:
        yield "CREATE INDEX temp.named ON local_input (sample COLLATE NOCASE, stamp DESC)"


def table_ddl(schema, name, layout, storage):
    suffix = " WITHOUT ROWID" if storage else ""
    return f"CREATE TABLE {quoted(schema)}.{quoted(name)} ({LAYOUTS[layout]})" + suffix


def declarations(item):
    yield "CREATE TABLE main.stock (id INTEGER PRIMARY KEY, code TEXT COLLATE NOCASE, flag INT)"
    yield table_ddl(item["attachment"], item["table"], item["layout"], item["storage"])
    yield table_ddl(item["attachment"], "collision", item["layout"], item["storage"])
    if item["temp"]:
        yield "CREATE TEMP TABLE local_input (sample TEXT, stamp INT)"
        yield f"CREATE TEMP TABLE {quoted(item['table'])} (row_value INTEGER PRIMARY KEY)"
    yield from explicit_ddl(item)


def appended_primary(terms, primary, *, inherit_direction):
    used = set(signature(terms))
    return tuple(value if inherit_direction else value._replace(descending=0)
                 for value in primary if (value.slot, value.collate) not in used)


def automatic_indexes(item, name):
    schema, storage = item["attachment"], item["storage"]
    created = physical(item["layout"])
    primary = distinct_terms(created[primary_ordinal(item["layout"]) - 1][1])
    for ordinal, (owns_table, key_terms) in enumerate(created, 1):
        is_storage = storage and owns_table
        if not storage:
            auxiliary = (Term(-1, None),)
        elif is_storage:
            key_terms = primary
            pk_slots = {value.slot for value in primary}
            auxiliary = tuple(term(slot, collate="BINARY") for slot in range(len(COLUMNS)) if slot not in pk_slots)
        else:
            auxiliary = appended_primary(key_terms, primary, inherit_direction=False)
        yield Index(schema, name, automatic(name, ordinal), key_terms, auxiliary, is_storage)


def indexes(item):
    rowid = (Term(-1, None),)
    code = Term(1, "code", 0, "NOCASE")
    flag = Term(2, "flag")
    yield Index("main", "stock", "named", (Term(1, "code", 1, "RTRIM"), flag), rowid)
    yield Index("main", "stock", "expression_index", (Term(-2, None, int(item["descending"])), code), rowid)
    yield Index("main", "stock", "collision", (flag, code._replace(collate="BINARY")), rowid)
    yield Index("main", "stock", "1", (flag,), rowid)
    yield Index("main", "stock", "+1", (code,), rowid)
    if item["temp"]:
        yield Index("temp", "local_input", "named", (Term(0, "sample", 0, "NOCASE"), Term(1, "stamp", 1)), rowid)
    explicit = (term(2, 1), term(0), term(1, collate="BINARY"))
    primary_key = distinct_terms(physical(item["layout"])[primary_ordinal(item["layout"]) - 1][1])
    auxiliary = appended_primary(explicit, primary_key, inherit_direction=True) if item["storage"] else rowid
    yield Index(item["attachment"], item["table"], "named", explicit, auxiliary)
    yield from automatic_indexes(item, item["table"])
    yield from automatic_indexes(item, "collision")
