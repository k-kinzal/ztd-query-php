"""Independently replay PostgreSQL signed range and distinctness observations."""

import run_server_postgres_signed_range_widths
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_signed_range_widths)
