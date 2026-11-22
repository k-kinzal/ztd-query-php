"""Closed SQLite uniqueness layouts, covering indexes and observable conflict transitions."""

from itertools import combinations, product
from random import Random

from sqlite_engine import typed

TABLE = "ir_unique"
FIXTURE = "CREATE TABLE ir_unique_marker (value INTEGER)"
COLLATIONS = ("BINARY", "NOCASE", "RTRIM")
POLICIES = (None, "ROLLBACK", "ABORT", "FAIL", "IGNORE", "REPLACE")
DIMENSIONS = ("schemas", "strict", "policies", "layouts")


def key(terms, conflict=None, *, kind="unique", inline=False):
    return {"kind": kind, "terms": [list(term) for term in terms], "inline": inline, "conflict": conflict,
            **({"autoIncrement": False} if kind == "primary" else {})}


def plan(columns, keys, *, schema="main", strict=False, without=False, table=TABLE):
    return {"operation": "create-table", "schema": schema, "table": table,
            "columns": [[name, kind, False, None, comparison] for name, kind, comparison in columns],
            "keys": keys, "strict": strict, "withoutRowid": without, "ifNotExists": False}


def fields(first="TEXT", collation="BINARY"):
    return [("first", first, collation), ("second", "TEXT", "BINARY"), ("stamp", "INTEGER", "BINARY")]


def layout(name, policy, schema="main", strict=False):
    first, second = (0, False, "BINARY"), (1, False, "BINARY")
    if name == "inline":
        return plan(fields(), [key([first], policy, inline=True)], schema=schema, strict=strict)
    if name == "inline-nocase":
        return plan(fields(collation="NOCASE"), [key([(0, False, "NOCASE")], policy, inline=True)],
                    schema=schema, strict=strict)
    if name == "table-binary-override":
        return plan(fields(collation="NOCASE"), [key([first], policy)], schema=schema, strict=strict)
    if name == "composite":
        return plan(fields(), [key([(1, True, "RTRIM"), (0, False, "NOCASE")], policy)],
                    schema=schema, strict=strict)
    if name == "two-separate":
        return plan(fields(), [key([first], policy, inline=True), key([(1, True, "RTRIM")], "IGNORE")],
                    schema=schema, strict=strict)
    if name == "equivalent-sort":
        return plan(fields(), [key([first], inline=True), key([(0, True, "BINARY")], policy)],
                    schema=schema, strict=strict)
    if name == "rowid-alias":
        return plan(fields(first="INTEGER"), [key([first], "IGNORE", kind="primary", inline=True), key([second], policy)],
                    schema=schema, strict=strict)
    if name == "without-rowid":
        return plan(fields(first="INT"), [key([first], kind="primary", inline=True), key([(1, True, "RTRIM")], policy)],
                    schema=schema, strict=strict, without=True)
    if name == "ordered-conflicts":
        return plan(fields(), [key([first], policy), key([(1, True, "BINARY")], "FAIL")], schema=schema, strict=strict)
    raise ValueError("Unknown uniqueness layout " + name)


def structure(item):
    return {**{name: item[name] for name in ("schema", "table", "strict", "withoutRowid", "ifNotExists", "keys")},
            "columns": [column[:3] for column in item["columns"]],
            "collations": [column[4] for column in item["columns"]]}


def finite_cases(domain):
    for schema, strict, policy, tag in product(*(domain[name] for name in DIMENSIONS)):
        yield layout(tag, policy, schema, strict)


def finite_ids(domain):
    return set(product(*(range(len(domain[name])) for name in DIMENSIONS)))


def finite_id(observed, domain):
    shape = {name: observed[name] for name in structure(layout("inline", None))}
    schema = domain["schemas"].index(shape["schema"])
    strict = domain["strict"].index(shape["strict"])
    match = [(schema, strict, mode, name)
             for name, tag in enumerate(domain["layouts"]) for mode, policy in enumerate(domain["policies"])
             if structure(layout(tag, policy, shape["schema"], shape["strict"])) == shape]
    assert len(match) == 1, shape
    return match[0]


def seeded(seed, count):
    rng = Random(seed)
    for index in range(count):
        width = rng.randrange(2, 7)
        schema, strict = rng.choice(("main", "temp")), bool(rng.getrandbits(1))
        without = index % 4 == 0
        primary = without or index % 4 == 1
        columns = [(f'column {i} {rng.choice(("é", "列", "label"))}', "TEXT", rng.choice(COLLATIONS))
                   for i in range(width - 1)]
        if primary:
            columns[0] = (columns[0][0], "INT" if without else "INTEGER", "BINARY")
        columns.append(("stamp", "INTEGER", "BINARY"))
        indexed = range(int(primary), width - 1)
        if not indexed:
            indexed = range(width - 1)
        inline = [key([(slot, False, columns[slot][2])], rng.choice(POLICIES), inline=True)
                  for slot in indexed if rng.randrange(3) == 0]
        if primary:
            inline.insert(0, key([(0, False, "BINARY")], rng.choice(POLICIES), kind="primary", inline=True))
        table = []
        existing = {tuple((slot, collation) for slot, _, collation in entry["terms"]): entry["conflict"]
                    for entry in inline}
        for _ in range(rng.randrange(1, 5)):
            slots = rng.sample(list(indexed), rng.randrange(1, min(3, len(indexed)) + 1))
            terms = [(slot, bool(rng.getrandbits(1)), rng.choice(COLLATIONS)) for slot in slots]
            identity = tuple((slot, collation) for slot, _, collation in terms)
            policy = existing[identity] if identity in existing else rng.choice(POLICIES)
            table.append(key(terms, policy))
            existing[identity] = policy
        yield plan(columns, inline + table, schema=schema, strict=strict, without=without)


def analyzed():
    yield "CREATE TABLE t (a INTEGER UNIQUE)", plan([("a", "INTEGER", "BINARY")],
                                                     [key([(0, False, "BINARY")], inline=True)], table="t")
    yield "CREATE TABLE ir_unique(first TEXT UNIQUE COLLATE NOCASE, second TEXT, stamp INTEGER)", layout("inline-nocase", None)
    for ordering in (True, False):
        first = "UNIQUE(first) ON CONFLICT IGNORE"
        second = "UNIQUE(second DESC) ON CONFLICT FAIL"
        ordered = [first, second] if ordering else [second, first]
        item = layout("ordered-conflicts", "IGNORE")
        if not ordering:
            item["keys"].reverse()
        yield "CREATE TABLE ir_unique(first TEXT, second TEXT, stamp INTEGER, " + ", ".join(ordered) + ")", item
    yield ("CREATE TABLE ir_unique(first INT PRIMARY KEY, second TEXT, stamp INTEGER, UNIQUE(second COLLATE RTRIM DESC)) WITHOUT ROWID",
           layout("without-rowid", None))
    yield ("CREATE TABLE ir_unique(first TEXT UNIQUE, second TEXT, stamp INTEGER, UNIQUE(first DESC) ON CONFLICT IGNORE)",
           layout("equivalent-sort", "IGNORE"))
    yield ("CREATE TABLE ir_unique(first TEXT COLLATE NOCASE, second TEXT, stamp INTEGER, UNIQUE(first COLLATE BINARY) ON CONFLICT ABORT)",
           layout("table-binary-override", "ABORT"))
    yield ("CREATE TABLE ir_unique(first TEXT UNIQUE ON CONFLICT IGNORE, second TEXT, stamp INTEGER, PRIMARY KEY(first))",
           plan(fields(), [key([(0, False, "BINARY")], "IGNORE", inline=True),
                           key([(0, False, "BINARY")], kind="primary")]))


def requests(specification):
    domain, budget = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(domain)):
        yield f"finite/{index}", item, item
    for seed in budget["seeds"]:
        for index, item in enumerate(seeded(seed, budget["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": []}, item


def negatives():
    first = [(0, False, "BINARY")]
    yield plan(fields(), [key([(9, False, "BINARY")])]), "rejected", "belongs to the declared column layout"
    yield plan(fields(), [key([(0, True, "BINARY")], inline=True)]), "rejected", "needs one ascending term"
    yield plan(fields(), [key(first), key(first, inline=True)]), "rejected", "precede table keys"
    yield plan(fields(), [key(first, "ABORT"), key([(0, True, "BINARY")], "IGNORE")]), "rejected", "conflicting explicit ON CONFLICT"
    yield {"sql": "CREATE TABLE t(a, UNIQUE(a) ON CONFLICT ABORT, UNIQUE(a) ON CONFLICT IGNORE)", "declarations": []}, "rejected", "conflicting explicit ON CONFLICT"
    yield {"sql": "CREATE TABLE t(a, UNIQUE(missing))", "declarations": []}, "analysis-rejected", "missing"
    yield {"sql": "CREATE TABLE t(a, UNIQUE(a COLLATE custom))", "declarations": []}, "rejected", "needs a builtin collation"


def rowid_alias(item, entry):
    if entry["kind"] != "primary" or item["withoutRowid"] or len(entry["terms"]) != 1:
        return False
    slot, descending, _ = entry["terms"][0]
    return item["columns"][slot][1] == "INTEGER" and not (entry["inline"] and descending)


def index_signature(entry):
    return tuple((slot, collation) for slot, _, collation in entry["terms"])


def native_indexes(item):
    indexes = []
    for entry in item["keys"]:
        if rowid_alias(item, entry):
            continue
        signature = index_signature(entry)
        equivalent = next((idx for idx in indexes if index_signature(idx) == signature), None)
        if equivalent is None:
            indexes.insert(0, {**entry, "ordinal": len(indexes) + 1})
        else:
            if equivalent["conflict"] is None:
                equivalent["conflict"] = entry["conflict"]
            if entry["kind"] == "primary":
                equivalent["kind"] = "primary"
        indexes.sort(key=lambda idx: idx["conflict"] == "REPLACE")
    return indexes


def typed_rows(rows):
    return [[typed(value) for value in row] for row in rows]


def index_name(item, index):
    return f"sqlite_autoindex_{item['table']}_{index['ordinal']}"


def catalog(item):
    keys = item["keys"]
    primary = next((entry for entry in keys if entry["kind"] == "primary"), None)
    pk_slots = [] if primary is None else list(dict.fromkeys(term[0] for term in primary["terms"]))
    alias = primary is not None and rowid_alias(item, primary)
    columns = item["columns"]
    required_key = item["strict"] or item["withoutRowid"]
    fields = [[slot, name, kind,
               int(not_null or (required_key and slot in pk_slots and not alias)),
               None, pk_slots.index(slot) + 1 if slot in pk_slots else 0, 0]
              for slot, (name, kind, not_null, _, _) in enumerate(columns)]
    indexes = native_indexes(item)
    index_list, details = [], {}
    for ordinal, index in enumerate(indexes):
        name = index_name(item, index)
        index_list.append([ordinal, name, 1, "pk" if index["kind"] == "primary" else "u", 0])
        parts = [[slot, columns[slot][0], int(descending), collation, 1]
                 for slot, descending, collation in index["terms"]]
        if not item["withoutRowid"]:
            parts.append([-1, None, 0, "BINARY", 0])
        elif index["kind"] == "primary":
            indexed = {term[0] for term in index["terms"]}
            parts.extend([slot, field[0], 0, "BINARY", 0] for slot, field in enumerate(columns) if slot not in indexed)
        else:
            in_index = set(index_signature(index))
            parts.extend([slot, columns[slot][0], int(descending), collation, 0]
                         for slot, descending, collation in primary["terms"] if (slot, collation) not in in_index)
        details[name] = typed_rows([[position, *part] for position, part in enumerate(parts)])
    table = [item["schema"], item["table"], "table", len(columns), int(item["withoutRowid"]), int(item["strict"])]
    return {"table": typed_rows([table]), "columns": typed_rows(fields), "list": typed_rows(index_list), "terms": details}


def row_values(item, number):
    return [number + index if field[1] in ("INT", "INTEGER") else f"letter{number}key{index}"
            for index, field in enumerate(item["columns"])]


def fixture_rows(item, simultaneous):
    base, other, first, duplicate, last = [row_values(item, number) for number in (10, 20, 30, 40, 50)]
    focus = [entry for entry in item["keys"] if entry["kind"] == "unique"]
    selected = item["keys"] if simultaneous else focus[:1]
    copies = {}
    for entry in selected:
        for slot, _, collation in entry["terms"]:
            copies.setdefault(slot, []).append(collation)
    for slot, collations in copies.items():
        value = base[slot]
        if type(value) is str and len(set(collations)) == 1:
            if collations[0] == "NOCASE":
                value = value.upper()
            if collations[0] == "RTRIM":
                value = value + " "
        duplicate[slot] = value

    return [base, other], [first, duplicate, last]


def nullable_pairs(item):
    primary = {slot for entry in item["keys"] if entry["kind"] == "primary"
               and (rowid_alias(item, entry) or item["strict"] or item["withoutRowid"])
               for slot, _, _ in entry["terms"]}
    sequence = 100
    for ordinal, entry in enumerate(item["keys"]):
        if entry["kind"] != "unique":
            continue
        terms = sorted({slot for slot, _, _ in entry["terms"]})
        nullable = [slot for slot in terms if slot not in primary and not item["columns"][slot][2]]
        for length in range(1, len(nullable) + 1):
            for mask in combinations(nullable, length):
                first, second = row_values(item, sequence), row_values(item, sequence + 1)
                sequence += 2
                for slot in terms:
                    if slot in mask:
                        first[slot] = second[slot] = None
                    elif slot not in primary:
                        second[slot] = first[slot]
                yield ordinal, list(mask), [first, second]


ASCII_LOWER = dict(zip(map(ord, "ABCDEFGHIJKLMNOPQRSTUVWXYZ"), "abcdefghijklmnopqrstuvwxyz"))


def normalized(value, collation):
    if type(value) is not str:
        return value
    if collation == "NOCASE":
        return value.translate(ASCII_LOWER)
    if collation == "RTRIM":
        return value.rstrip(" ")
    return value


def collisions(rows, row, entry):
    if any(row[slot] is None for slot, _, _ in entry["terms"]):
        return []
    return [index for index, prior in enumerate(rows)
            if all(normalized(row[slot], collation) == normalized(prior[slot], collation)
                   for slot, _, collation in entry["terms"])]


def transition(item, before, sequence, explicit):
    indexes = [entry for entry in item["keys"] if rowid_alias(item, entry)] + native_indexes(item)
    rows = list(before)
    changes = 0
    for row in sequence:
        replaced = set()
        skip = False
        for entry in indexes:
            matches = collisions(rows, row, entry)
            if not matches:
                continue
            action = entry["conflict"] or "ABORT"
            if action == "REPLACE":
                replaced.update(matches)
                continue
            if action == "IGNORE":
                skip = True
                break
            names = ", ".join(f"{item['table']}.{item['columns'][slot][0]}" for slot, _, _ in entry["terms"])
            error = {"code": 19, "message": f"UNIQUE constraint failed: {names}"}
            if action == "FAIL":
                return rows, changes, error, explicit
            assert action in ("ABORT", "ROLLBACK")
            return before, 0, error, explicit and action == "ABORT"
        if not skip:
            rows = [value for index, value in enumerate(rows) if index not in replaced]
            rows.append(row)
            changes += 1
    return rows, changes, None, explicit


def stored_rows(rows):
    return typed_rows(sorted(rows, key=lambda row: (row[-1] is not None, row[-1])))


def expected(item, explicit, simultaneous):
    base, writes = fixture_rows(item, simultaneous)
    nullable = []
    for key, mask, pair in nullable_pairs(item):
        visible, changes, error, _ = transition(item, base, pair, False)
        nullable.append({"key": key, "mask": mask, "write": {"changes": changes, "error": error}, "state": stored_rows(visible)})
    rows, changes, error, active = transition(item, base, writes, explicit)
    return {"catalog": catalog(item), "nullableWrites": nullable, "conflict": {"changes": changes, "error": error},
            "state": stored_rows(rows), "active": active, "marker": typed_rows([[73]]) if active else []}
