"""Independently replay PostgreSQL signed comparison-width evidence."""

import run_server_postgres_signed_comparison_widths
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_signed_comparison_widths)
