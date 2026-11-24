"""Replay the native PostgreSQL namespace-change comparisons."""

import run_server_postgres_alter_schema
from combine_server_join_evidence import main

if __name__ == "__main__":
    main(run_server_postgres_alter_schema)
