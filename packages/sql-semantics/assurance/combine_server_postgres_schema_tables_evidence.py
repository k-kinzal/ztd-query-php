"""Replay PostgreSQL's checked namespace composition receipts by release."""

import run_server_postgres_schema_tables
from combine_server_join_evidence import main

if __name__ == "__main__":
    main(run_server_postgres_schema_tables)
