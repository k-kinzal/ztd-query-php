"""Independent recipes for B-trees whose text collation comes from the selected column."""

from itertools import product
from random import Random

import postgres_btree_text_cases as text
from postgres_reindex_cases import qualified, quoted

PLACEMENTS = {"persistent": "collated_app", "temporary": "pg_temp", "like-copy": "collated_app"}
ROLES = {"one-text-key": [2], "two-text-keys": [2, 3], "mixed-integer-text": [1, 2]}
ORDERS = ("ASC NULLS LAST", "DESC NULLS FIRST")
FORMS = ("omitted", "qualified-text-ops")
ANALYSIS = ("analyze-inherited", "analyze-explicit")
DIRECT = "explicit-construction"
KINDS = ["INTEGER", "INTEGER", "TEXT", "TEXT"]
SEARCH = ["pg_temp", "pg_catalog", "collated_decoy", "collated_app", "public"]


def coordinates(bound):
    return product(*(bound[name] for name in ("placements", "layouts", "keyDirections", "classSpelling")))


def identities():
    return set(product(PLACEMENTS, ROLES, ORDERS, FORMS))


def column_heading(columns, policies):
    collations = {"C": ' COLLATE pg_catalog."C"', "POSIX": ' COLLATE pg_catalog."POSIX"',
                  "local": ' COLLATE "C"', None: ""}
    return ", ".join(f"{quoted(name)} {kind}{collations[policy]}" for name, kind, policy in zip(columns, KINDS, policies))


def fixture(item, placement, policies):
    owner = qualified(item["schema"], item["table"])
    target = quoted(item["table"]) if placement == "temporary" else owner
    fields = item["columns"]
    decoy = qualified("collated_decoy", item["table"])
    setup = ["CREATE SCHEMA collated_decoy",
             f"CREATE TABLE {decoy} (" + column_heading(fields, [None, None, "POSIX", "C"]) + ")"]
    heading = column_heading(fields, policies)
    if placement == "temporary":
        return [*setup, f"CREATE TEMP TABLE {target} ({heading})"]
    setup.append("CREATE SCHEMA collated_app")
    if placement == "like-copy":
        source = qualified(item["schema"], "source_table")
        return [*setup, f"CREATE TABLE {source} ({heading})", f"CREATE TABLE {owner} (LIKE {source})"]
    return [*setup, f"CREATE TABLE {owner} ({heading})"]


def case(tag, placement, layout, direction, form, route, *, slots=None, include=None, null_test=None,
         columns=None, table="orders", name="picked", policy="plain", collations=None, data=None, proposals=None):
    item = text.case(tag, layout, direction, policy, "omitted",
                     choices=(ROLES[layout] if slots is None else slots, [] if include is None else include, null_test),
                     columns=columns, table=table, name=name, data=data, proposals=proposals)
    item.update(schema=PLACEMENTS[placement], coordinate=[placement, layout, direction, form, route], searchPath=SEARCH)
    item["setup"] = fixture(item, placement, [None, None, "C", "C"] if collations is None else collations)
    return item


def statement(item):
    command = text.statement(item)
    form, route = item["coordinate"][-2:]
    if route == ANALYSIS[0]:
        command = command.replace(text.COLLATION, "")
    if form == FORMS[0]:
        command = command.replace(text.OPERATOR, "")
    return command


def analyzed(item):
    return {"dialect": "postgres", "release": item["tag"], "sql": statement(item),
            "declarations": item["setup"], "schemaPath": item["searchPath"]}


def off_profile(tag, purpose):
    policies = {"neighbor": [None, None, "C", "POSIX"], "posix": [None, None, "POSIX", "POSIX"],
                "default": [None, None, None, None], "unqualified": [None, None, "local", None],
                "decoy": [None, None, "POSIX", None]}
    slot = 3 if purpose == "neighbor" else 2
    item = case(tag, "persistent", "one-text-key", ORDERS[0], FORMS[0], ANALYSIS[0],
                slots=[slot], collations=policies[purpose])
    item["offProfile"] = purpose
    if purpose == "decoy":
        name = qualified("collated_decoy", item["table"])
        item["setup"][1] = f"CREATE TABLE {name} (" + column_heading(item["columns"], [None, None, "C", "C"]) + ")"
    if purpose == "unqualified":
        item["searchPath"] = ["pg_temp", "collated_decoy", "collated_app", "public", "pg_catalog"]
        item["setup"] = [definition.replace(" TEXT", " pg_catalog.text") for definition in item["setup"]]
    return item


def expected(item, *, native_original=False):
    result = text.expected(item)
    if native_original and item.get("offProfile") in ("neighbor", "posix", "decoy"):
        result["collations"][0] = ["pg_catalog", "POSIX"]
    return result


def generated(tag, seed, count, direct=False):
    rng = Random(seed)
    for ordinal in range(count):
        placement = tuple(PLACEMENTS)[ordinal % len(PLACEMENTS)]
        layout = rng.choice(tuple(ROLES))
        route = DIRECT if direct else ANALYSIS[ordinal % len(ANALYSIS)]
        slots = [*ROLES[layout]]
        if rng.randrange(2):
            slots.append(rng.choice((1, 2, 3)))
        rng.shuffle(slots)
        includes = [rng.choice((1, 2, 3)) for _ in range(rng.randrange(4))]
        nonce = rng.randrange(400, 9999)
        values = [[id, None if bucket is None else bucket + nonce, code, payload] for id, bucket, code, payload in text.DATA]
        proposals = [[id, None if bucket is None else bucket + nonce, code, payload] for id, bucket, code, payload in text.PROPOSALS]
        yield case(tag, placement, layout, rng.choice(ORDERS), rng.choice(FORMS), route, slots=slots, include=includes,
                   null_test=rng.choice((None, 3)), table=f'order."{nonce}', columns=[f'{field}."{nonce}' for field in text.COLUMNS],
                   name=f'col.鍵_{nonce}', data=values, proposals=proposals)
