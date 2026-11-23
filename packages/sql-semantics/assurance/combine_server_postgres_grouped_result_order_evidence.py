"""Independently replay PostgreSQL grouped result order evidence."""

import run_server_postgres_grouped_result_order
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_grouped_result_order)
