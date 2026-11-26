"""Replay PostgreSQL named output order and canonical ordinal observations."""

import run_server_postgres_output_order_names
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_output_order_names)
