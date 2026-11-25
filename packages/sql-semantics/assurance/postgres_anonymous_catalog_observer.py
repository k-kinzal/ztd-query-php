"""Observe the native owner, index effects and row state after an anonymous prefix."""

import json
from platform import python_version
import re
import sys

from postgres_anonymous_catalog_cases import DECOY
from postgres_create_btree_observer import data_literal, literal, qualified, quoted
from postgres_libpq import LIB, Session
from run import require

IDENTIFIER = r'"(?:[^"\x00\r\n]|"")+"'
RELATION = rf'{IDENTIFIER}\.{IDENTIFIER}'
KEY = rf'{IDENTIFIER}(?: (?:ASC|DESC) NULLS (?:FIRST|LAST))?'
ADMITTED = re.compile(
    rf'(?:DROP INDEX {RELATION}(?: RESTRICT| CASCADE)?|REINDEX INDEX {RELATION}|'
    rf'CREATE INDEX (?:{IDENTIFIER} )?ON {RELATION} USING btree \({KEY}(?:, {KEY})*\)'
    rf'(?: INCLUDE \({IDENTIFIER}(?:, {IDENTIFIER})*\))?)'
)

OWNER = """SELECT CASE WHEN n.oid=pg_my_temp_schema() THEN 'pg_temp' ELSE n.nspname END AS schema,c.relname AS name
 FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE c.oid=to_regclass({relation})"""

INDEXES = """SELECT c.relname,m.amname,i.indnkeyatts,i.indkey::text AS positions,i.indoption::text AS options,i.indisvalid,i.indisunique,
 ARRAY(SELECT a.attname FROM pg_attribute a WHERE a.attrelid=c.oid AND a.attnum>0 ORDER BY a.attnum) AS labels,
 ARRAY(SELECT o.opcname FROM unnest(i.indclass::oid[]) WITH ORDINALITY p(oid,n)
       JOIN pg_opclass o ON o.oid=p.oid ORDER BY p.n) AS classes, pg_relation_filenode(c.oid) AS page
 FROM pg_index i JOIN pg_class c ON i.indexrelid=c.oid JOIN pg_am m ON m.oid=c.relam
 WHERE i.indrelid=to_regclass({relation}) ORDER BY i.indexrelid"""


def snapshot(db, scope, fields):
    table = qualified(*scope)
    relation = data_literal(table)
    projection = ", ".join(map(quoted, fields))
    order = quoted(fields[-1])
    values = db.execute(
        "SELECT (SELECT row_to_json(owner) FROM (" + OWNER.format(relation=relation) + ") owner),"
        " to_json(ARRAY(SELECT row_to_json(idx) FROM (" + INDEXES.format(relation=relation) + ") idx)), "
        f" (SELECT json_agg(r ORDER BY r.ordinal) FROM (SELECT row_number() OVER (ORDER BY {order}) "
        f"AS ordinal, ARRAY[{projection}] AS fields FROM {table}) r)"
    )[0]
    require(values[0] is not None and values[2] is not None, "anonymous-catalog-native-owner-missing", scope)
    declared = json.loads(values[0])
    owner = [declared["schema"], declared["name"]]
    indexes, pages = [], {}
    for row in json.loads(values[1]):
        indexes.append([row["relname"], row["amname"], row["indnkeyatts"], list(map(int, row["positions"].split())),
                        row["labels"], list(map(int, row["options"].split())), row["classes"],
                        row["indisvalid"], row["indisunique"]])
        pages[(owner[0], row["relname"])] = row["page"]
    return [owner, indexes, [row["fields"] for row in json.loads(values[2])]], pages


def state(db, item):
    primary, main = snapshot(db, item["owner"], item["columns"])
    decoy, alternate = snapshot(db, [DECOY, "orders"], ["id"])
    return [primary, decoy], {**main, **alternate}


def observe(db, item, statement):
    if len(statement) > 8192 or ADMITTED.fullmatch(statement) is None:
        return {"status": "outside-anonymous-catalog-observer"}
    table = qualified(*item["owner"])
    data = ", ".join("(" + ", ".join(map(literal, row)) + ")" for row in item["data"])
    db.execute("BEGIN")
    try:
        db.execute(";".join(["SET LOCAL client_min_messages=warning", *item["setup"],
                            f"INSERT INTO {table} VALUES {data}", f"INSERT INTO {DECOY}.orders VALUES (976)"]))
        before, pages = state(db, item)
        result = db.run(statement, diagnostic_error=True)
        if result["sqlState"] != "00000":
            return {"status": "anonymous-catalog-native-refusal", "before": before, "result": result}
        after, later = state(db, item)
        changed = [list(key) for key in sorted(pages.keys() & later.keys()) if pages[key] != later[key]]
        return {"status": "anonymous-catalog-observation", "sqlState": result["sqlState"], "command": result["command"],
                "before": before, "after": after, "changedFiles": changed}
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
            reply = [run(record) for record in entries] if isinstance(entries, list) else run(entries)
            print(json.dumps(reply, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
