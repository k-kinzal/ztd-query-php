"""Independent source, assignment and new-row projection cases for SQLite INSERT."""

from itertools import product
from random import Random

from run_sqlite_insert import case as values, seeded as values_samples
from sqlite_insert_input_cases import DECLARATIONS as WRITE_DECLARATIONS, FIXTURE as WRITE, defaults, expected as effects
from sqlite_insert_query_cases import READ_DECLARATIONS, SCHEMA, case as from_query, column, query, seeded as query_samples
from sqlite_result_cases import integer

DECLARATIONS = WRITE_DECLARATIONS + list(READ_DECLARATIONS)
AFTER_PAYLOAD = 113
HOOK = "END;\n"
assert WRITE.count(HOOK) == 1
FIXTURE = SCHEMA + WRITE.replace(HOOK, f"  UPDATE ir_insert SET payload = {AFTER_PAYLOAD} WHERE rowid = NEW.rowid;\n" + HOOK)
COLUMNS = ("id", "payload", "computed")
LAYOUTS = {
    "absent": (),
    "computed": (("computed", "result"),),
    "all": (("id", "id"), ("payload", "payload"), ("computed", "computed")),
    "repeated": (("computed", "same"), ("payload", "same"), ("computed", "copy")),
}


def with_returning(item, returning, layout=COLUMNS):
    target = [*item["target"][:2], list(layout)]
    writes = [column(layout.index(name)) for name in item["target"][2]]
    return {**item, "target": target, "writes": writes, "returning": [[column(layout.index(name)), label] for name, label in returning]}


def finite_cases(domain):
    row = [integer(29), integer(5)]
    for schema, policy, role, output in product(domain["schemas"], domain["policies"], domain["inputs"], domain["layouts"]):
        if role == "defaults":
            item = defaults(schema, policy)
        elif role == "values":
            item = values([row], schema, ("payload", "id"), policy)
        else:
            projection = query(None, [[row[0], "v"], [row[1], "i"]])
            item = from_query(projection, ("payload", "id"), schema, policy)
        yield with_returning(item, LAYOUTS[output])


def role(item):
    return "defaults" if item.get("defaults") else "select" if "query" in item else "values"


def finite_ids(domain):
    return set(product(domain["schemas"], domain["policies"], domain["inputs"], domain["layouts"]))


def finite_id(reply):
    _, _, columns = reply["target"]
    actual = [(columns[value[1]], label) for value, label in reply["returning"]]
    layout = next(name for name, fields in LAYOUTS.items() if tuple(actual) == fields)
    return reply["target"][0], reply["conflict"], role(reply), layout


def seeded(domain, seed, repetitions):
    rng = Random(seed ^ 44623)
    value_inputs = iter(values_samples(seed, repetitions))
    query_inputs = iter(query_samples(seed, repetitions))
    for _ in range(repetitions):
        input_role = rng.choice(domain["inputs"])
        item = {
            "defaults": lambda: defaults(rng.choice(domain["schemas"]), rng.choice(domain["policies"])),
            "values": lambda: next(value_inputs),
            "select": lambda: next(query_inputs),
        }[input_role]()
        names = rng.sample(COLUMNS, len(COLUMNS))
        returning = [(rng.choice(names), rng.choice(("out", "out", "値", 'needs"quote'))) for _ in range(rng.randrange(6))]
        yield with_returning(item, returning, names)


def analyzed():
    yield ("INSERT INTO temp.ir_insert DEFAULT VALUES RETURNING *",
           with_returning(defaults("temp"), LAYOUTS["all"]))
    yield ("INSERT OR IGNORE INTO main.ir_insert VALUES (4, 'ok'), (1, 'duplicate') RETURNING *, computed AS amount",
           with_returning(values([[integer(4), ["text", "ok"]], [integer(1), ["text", "duplicate"]]], conflict="ignore"),
                          (*LAYOUTS["all"], ("computed", "amount"))))
    projection = query(None, [[["parameter", "02"], "p"], [integer(6), "i"]], integer(1), bindings={"2": "text"})
    yield ("INSERT OR ABORT INTO ir_insert (payload,id) SELECT ?02 AS p, 6 AS i WHERE 1 RETURNING computed AS result, payload",
           with_returning(from_query(projection, ("payload", "id"), "temp", "abort"),
                          (("computed", "result"), ("payload", "payload")), ("payload", "id", "computed")))
    yield ("INSERT INTO main.ir_insert (id) VALUES (6) RETURNING computed AS default_generated, payload AS default_payload",
           with_returning(values([[integer(6)]], columns=("id",)),
                          (("computed", "default_generated"), ("payload", "default_payload")), ("id", "computed", "payload")))


def boundaries():
    yield with_returning(values([[integer(6)]], columns=("id",)), LAYOUTS["computed"] * 2000)


def negatives():
    item = with_returning(values([[integer(4), integer(7)]]), LAYOUTS["all"])
    for writes, reason in (
        ([column(3), column(1)], "A supplied slot must belong to the INSERT target"),
        ([column(0, 1), column(1)], "A supplied slot must belong to the INSERT target"),
        ([column(0), column(0)], "cannot repeat a supplied column"),
    ):
        yield {**item, "writes": writes}, reason
    yield {**item, "returning": [[column(3), "absent"]]}, "A returned slot must belong to the INSERT target"
    yield {**item, "returning": [[column(2), "many"]] * 2001}, "can return at most 2000 fields"
    for expression in ("a.id", "count(id)"):
        yield ({"sql": f"INSERT INTO main.ir_insert AS a VALUES (6, 5) RETURNING {expression}", "declarations": DECLARATIONS},
               "requires an unqualified target column")


def expected(item):
    def modify_after(row):
        return row[0], AFTER_PAYLOAD, 2 * AFTER_PAYLOAD

    return effects(item, modify_after)
