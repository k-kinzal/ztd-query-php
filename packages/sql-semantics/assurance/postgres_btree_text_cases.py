"""Independent finite and seeded models for native PostgreSQL text B-tree keys."""

from functools import cmp_to_key
from itertools import product
from random import Random

from postgres_create_btree_cases import published
from postgres_reindex_cases import qualified, quoted

DATA = [[1, 7, "A", "z"], [2, 2, "a", None], [3, 3, "é", "x"], [4, 4, "e\u0301", "m"],
        [5, None, None, "p"], [6, 5, "完", None], [7, 8, "ß", "a"]]
PROPOSALS = [[8, 7, "A", "z"], [9, 7, "A", None], [10, None, None, "p"], [11, 9, "e\u0300", "p"]]
COLUMNS = ["id", "bucket", "code", "payload"]
KINDS = ["int4", "int4", "text", "text"]
COLLATION = ' COLLATE pg_catalog."C"'
OPERATOR = " pg_catalog.text_ops"
ORDERS = ("ASC NULLS LAST", "ASC NULLS FIRST", "DESC NULLS LAST", "DESC NULLS FIRST")
UNIQUE = ("plain", "distinct-null", "equal-null")
FORMS = ("omitted", "stock-qualified")
TEXT_LAYOUTS = {"text-first": ([2], [1], None), "mixed-int-first": ([1, 2], [3], None),
                "two-text-keys": ([2, 3], [1], None)}
AUX_LAYOUTS = {"text-include": ([1], [2, 3], None), "text-null-test": ([1], [], 3)}
SCOPE = "pg_temp"


def coordinates(bound):
    policies = bound["orders"], bound["uniqueness"]
    yield from product(bound["textKeyLayouts"], *policies, bound["textClassSpelling"])
    yield from product(bound["auxiliaryTextLayouts"], *policies, FORMS[:1])


def identities():
    return set(product(TEXT_LAYOUTS, ORDERS, UNIQUE, FORMS)) | set(product(AUX_LAYOUTS, ORDERS, UNIQUE, FORMS[:1]))


def definition(item):
    name, fields = item["table"], item["columns"]
    collated = ' TEXT COLLATE pg_catalog."POSIX"'
    heading = [quoted(fields[0]) + " INTEGER NOT NULL", quoted(fields[1]) + " INTEGER",
               quoted(fields[2]) + collated, quoted(fields[3]) + " TEXT"]
    return f"CREATE TEMP TABLE {quoted(name)} (" + ", ".join(heading) + ")"


def comparison(column, term, explicit=True):
    order = term[1]
    optional = COLLATION + (OPERATOR if explicit else "") if len(term) == 3 else ""
    return quoted(column) + optional + " " + order


def filtering(item):
    slot = item["condition"]
    return "" if slot is None else " WHERE (" + quoted(item["columns"][slot]) + " IS NOT NULL)"


def statement(item, *, original=False):
    keys = item["keys"]
    names = item["columns"]
    explicit = not original or item["coordinate"][-1] == "stock-qualified"
    rendered = [comparison(names[term[0]], term, explicit) for term in keys]
    cover = item["included"]
    included = " INCLUDE (" + ", ".join(quoted(names[slot]) for slot in cover) + ")" if cover else ""
    unique = "UNIQUE " if item["policy"] != "plain" else ""
    nulls = " NULLS NOT DISTINCT" if item["policy"] == "equal-null" else ""
    return f"CREATE {unique}INDEX {quoted(item['name'])} ON {qualified(item.get('schema', SCOPE), item['table'])} USING btree (" + (
        ", ".join(rendered) + ")" + included + nulls + filtering(item))


def case(tag, layout, order, policy, form, *, choices=None, order_list=None, table="txt_cases", name="checked_native",
         columns=None, data=None, proposals=None):
    keys, include, condition = ({**TEXT_LAYOUTS, **AUX_LAYOUTS}[layout] if choices is None else choices)
    orders = [order] * len(keys) if order_list is None else order_list
    return {
        "coordinate": [layout, order, policy, form], "tag": tag, "table": table, "name": name,
        "columns": COLUMNS if columns is None else columns, "keys": [
            [slot, sorting, *(["C"] if slot >= 2 else [])] for slot, sorting in zip(keys, orders)],
        "included": include, "condition": condition, "policy": policy,
        "data": DATA if data is None else data, "proposals": PROPOSALS if proposals is None else proposals,
    }


def analyzed(item):
    return {"dialect": "postgres", "release": item["tag"], "sql": statement(item, original=True),
            "declarations": [definition(item)]}


def direct(item):
    return {"dialect": "postgres", "release": item["tag"], "createBtree": {
        "name": item["name"], "table": [item.get("schema", SCOPE), item["table"], item["columns"], KINDS, False],
        "keys": item["keys"], "included": item["included"],
        "predicate": None if item["condition"] is None else [item["condition"], True],
        "presence": "vacant", "ifNotExists": False, "concurrently": False,
        "unique": item["policy"] != "plain", "nullsNotDistinct": item["policy"] == "equal-null",
    }}


def graph(item):
    return published(direct(item)["createBtree"])


def by_order(terms):
    def compare(left, right):
        for slot, order, *_ in terms:
            a, b = left[slot], right[slot]
            if a is None or b is None:
                if a is b:
                    continue
                return (-1 if a is None else 1) * (1 if order.endswith("FIRST") else -1)
            if isinstance(a, str):
                a, b = a.encode("utf-8"), b.encode("utf-8")
            if a != b:
                return (-1 if a < b else 1) * (1 if order.startswith("ASC") else -1)
        return (left[0] > right[0]) - (left[0] < right[0])
    return compare


def included(item, row):
    return item["condition"] is None or row[item["condition"]] is not None


def writes(item):
    state, statuses = item["data"].copy(), []
    slots, policy = [term[0] for term in item["keys"]], item["policy"]
    for proposed in item["proposals"]:
        values = [proposed[slot] for slot in slots]
        conflict = policy != "plain" and included(item, proposed) and (policy == "equal-null" or None not in values) and any(
            included(item, prior) and [prior[slot] for slot in slots] == values for prior in state)
        statuses.append("23505" if conflict else "00000")
        if not conflict:
            state.append(proposed)
    return statuses


def expected(item):
    keys, data = item["keys"], item["data"]
    positions = [term[0] for term in keys] + item["included"]
    return {
        "status": "postgres-text-btree", "command": "CREATE INDEX", "sqlState": "00000",
        "owner": [item.get("schema", SCOPE), item["table"]], "index": item["name"], "heading": [[field, kind] for field, kind in zip(item["columns"], KINDS)],
        "positions": [slot + 1 for slot in positions], "options": [
            int(term[1].startswith("DESC")) + 2 * int(term[1].endswith("FIRST")) for term in keys],
        "collations": [["pg_catalog", "C"] if len(term) == 3 else None for term in keys],
        "classes": [["pg_catalog", "text_ops" if len(term) == 3 else "int4_ops"] for term in keys],
        "unique": item["policy"] != "plain", "nullsNotDistinct": item["policy"] == "equal-null",
        "rows": sorted((record for record in data if included(item, record)), key=cmp_to_key(by_order(keys))),
        "writes": writes(item),
    }


def generated(tag, seed, count):
    rng = Random(seed)
    for ordinal in range(count):
        layout = ("text", "include", "predicate", "mixed", "duplicate")[ordinal % 5]
        keys = {"text": [2], "include": [1], "predicate": [1], "mixed": [1, 2], "duplicate": [2, 2, 3]}[layout]
        if layout in ("text", "mixed") and rng.randrange(2):
            keys += [rng.choice((1, 2, 3))]
        rng.shuffle(keys)
        covering = [rng.choice((1, 2, 3)) for _ in range(rng.randrange(4))]
        if layout == "include":
            covering.append(3)
        condition = 3 if layout == "predicate" else rng.choice((None, None, 2, 3))
        # Inject a seed-dependent offset without shrinking or duplicating the admitted initial key values.
        nonce = rng.randrange(100, 9999)
        values = [[id, None if bucket is None else bucket + nonce, code, payload] for id, bucket, code, payload in DATA]
        proposals = [[id, None if bucket is None else bucket + nonce, code, payload] for id, bucket, code, payload in PROPOSALS]
        orders = [rng.choice(ORDERS) for _ in keys]
        yield case(tag, layout, orders[0], UNIQUE[ordinal % len(UNIQUE)], rng.choice(FORMS),
                   choices=(keys, covering, condition), order_list=orders, table=f'owner."{nonce}',
                   name=f'序.{nonce}', columns=[f'{base}."{nonce}' for base in COLUMNS], data=values, proposals=proposals)
