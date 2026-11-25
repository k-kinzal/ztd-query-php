"""Read native text index identities, typed rows, order, and write effects."""

import json
from platform import python_version
import re
import sys

from postgres_btree_text_cases import ORDERS, SCOPE, definition
from postgres_create_btree_observer import data_literal, literal, qualified, quoted
from postgres_libpq import LIB, Session
from run import require

IDENTIFIER = r'"(?:[^"\r\n\x00]|"")+"'
RELATION = rf'{IDENTIFIER}\.{IDENTIFIER}'
KEY = rf'{IDENTIFIER}(?: COLLATE pg_catalog\."C")?(?: pg_catalog\.text_ops)? (?:ASC|DESC) NULLS (?:FIRST|LAST)'
FILTER = rf'\({IDENTIFIER} IS NOT NULL\)'
ADMITTED = re.compile(
    rf'CREATE (?:UNIQUE )?INDEX {IDENTIFIER} ON {RELATION} USING btree \({KEY}(?:, {KEY})*\)'
    rf'(?: INCLUDE \({IDENTIFIER}(?:, {IDENTIFIER})*\))?(?: NULLS NOT DISTINCT)?(?: WHERE {FILTER})?'
)

CATALOG = """SELECT json_build_object(
 'owner',json_build_array(CASE WHEN n.oid=pg_my_temp_schema() THEN 'pg_temp' ELSE n.nspname END,t.relname),
 'index',c.relname,
 'positions',string_to_array(i.indkey::text,' ')::integer[],
 'options',string_to_array(i.indoption::text,' ')::integer[],
 'collations',(SELECT json_agg(CASE WHEN l.oid IS NULL THEN 'null'::json ELSE json_build_array(ln.nspname,l.collname) END ORDER BY k.ord)
               FROM unnest(i.indcollation::oid[]) WITH ORDINALITY k(oid,ord)
               LEFT JOIN pg_collation l ON l.oid=k.oid LEFT JOIN pg_namespace ln ON ln.oid=l.collnamespace),
 'classes',(SELECT json_agg(json_build_array(cn.nspname,op.opcname) ORDER BY k.ord)
            FROM unnest(i.indclass::oid[]) WITH ORDINALITY k(oid,ord)
            JOIN pg_opclass op ON op.oid=k.oid JOIN pg_namespace cn ON cn.oid=op.opcnamespace),
 'unique',i.indisunique,'nullsNotDistinct',i.indnullsnotdistinct,
 'heading',(SELECT json_agg(json_build_array(a.attname,ty.typname) ORDER BY a.attnum)
            FROM pg_attribute a JOIN pg_type ty ON ty.oid=a.atttypid
            WHERE a.attrelid=t.oid AND a.attnum>0 AND NOT a.attisdropped),
 'predicate',pg_get_expr(i.indpred,i.indrelid))
 FROM pg_index i JOIN pg_class c ON c.oid=i.indexrelid
 JOIN pg_class t ON t.oid=i.indrelid JOIN pg_namespace n ON n.oid=t.relnamespace
 WHERE i.indexrelid=to_regclass({index})"""

ORDER_BITS = [ORDERS[0], ORDERS[2], ORDERS[1], ORDERS[3]]


def row(values):
    return "(" + ", ".join(data_literal(value) if type(value) is str else literal(value) for value in values) + ")"


def observe_order(db, observation, table):
    names = [field for field, _ in observation["heading"]]
    positions = observation["positions"]
    paths = []
    for slot, direction, collation in zip(positions, observation["options"], observation["collations"]):
        require(0 < slot <= len(names) and 0 <= direction < len(ORDER_BITS), "postgres-text-btree-native-order", observation)
        paths.append(quoted(names[slot - 1]) + ((" COLLATE " + qualified(*collation)) if collation else "") +
                     " " + ORDER_BITS[direction])
    predicate = observation.pop("predicate")
    if predicate is not None:
        forms = {f"({form} IS NOT NULL)" for name in names for form in (name, quoted(name))}
        require(predicate in forms, "postgres-text-btree-native-predicate", predicate)
    projection = ", ".join(map(quoted, names))
    ordering = ", ".join([*paths, quoted(names[0])])
    values = "SELECT json_build_array(" + projection + ") FROM " + table
    if predicate is not None:
        values += " WHERE " + predicate
    values += " ORDER BY " + ordering
    return [json.loads(value) for (value,) in db.execute(values)]


def observe_writes(db, table, proposals):
    verdicts = []
    for proposal in proposals:
        db.execute("SAVEPOINT text_index_write")
        outcome = db.run(f"INSERT INTO {table} VALUES {row(proposal)}", diagnostic_error=True)
        if outcome["sqlState"] != "00000":
            db.execute("ROLLBACK TO SAVEPOINT text_index_write")
        db.execute("RELEASE SAVEPOINT text_index_write")
        verdicts.append(outcome["sqlState"])
    return verdicts


def observe(db, item, sql):
    if len(sql) > 8192 or ADMITTED.fullmatch(sql) is None:
        return {"status": "outside-postgres-text-btree-observer"}
    table = qualified(SCOPE, item["table"])
    name = qualified(SCOPE, item["name"])
    rows = ", ".join(map(row, item["data"]))
    db.execute("BEGIN")
    try:
        db.execute(definition(item) + f"; INSERT INTO {table} VALUES {rows}")
        reply = db.run(sql, diagnostic_error=True)
        if reply["sqlState"] != "00000":
            return {"status": "postgres-text-btree-native-refusal", "result": reply}
        found = db.execute(CATALOG.format(index=data_literal(name)))
        require(len(found) == 1, "postgres-text-btree-catalog-missing", {"observation": found, "sql": sql})
        observed = json.loads(found[0][0])
        ordered = observe_order(db, observed, table)
        return {"status": "postgres-text-btree", "command": reply["command"], "sqlState": reply["sqlState"], **observed,
                "rows": ordered, "writes": observe_writes(db, table, item["proposals"])}
    finally:
        db.execute("ROLLBACK")


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + name)[0][0] for name in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)

        def run(record):
            return observe(db, record["trial"], record["sql"])

        for line in sys.stdin:
            entries = json.loads(line)
            result = [run(entry) for entry in entries] if isinstance(entries, list) else run(entries)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
