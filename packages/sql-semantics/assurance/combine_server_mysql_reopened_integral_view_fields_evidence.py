"""Replay the exact MySQL reopened signed read ledgers."""

from combine_server_join_evidence import main
import run_server_mysql_reopened_integral_view_fields


if __name__ == "__main__":
    main(run_server_mysql_reopened_integral_view_fields)
