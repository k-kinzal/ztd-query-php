"""Independently replay all native MySQL signed comparison-width profiles."""

from combine_server_join_evidence import main
import run_server_mysql_signed_comparison_widths


if __name__ == "__main__":
    main(run_server_mysql_signed_comparison_widths)
