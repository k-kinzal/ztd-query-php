"""Replay PostgreSQL namespace removal effects for both pinned releases."""

import run_server_postgres_drop_schema
from combine_server_join_evidence import main

if __name__ == "__main__":
    main(run_server_postgres_drop_schema)
