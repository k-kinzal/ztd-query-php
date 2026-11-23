"""Independently replay native PostgreSQL source order evidence."""

import run_server_postgres_source_order
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_source_order)
