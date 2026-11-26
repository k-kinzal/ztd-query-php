"""Replay native schema creation evidence for the pinned PostgreSQL releases."""

import run_server_postgres_create_schema
from combine_server_join_evidence import main

if __name__ == "__main__":
    main(run_server_postgres_create_schema)
