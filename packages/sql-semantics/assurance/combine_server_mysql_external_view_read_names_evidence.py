"""Recalculate persistent view read results and binding evidence independently."""

from combine_server_join_evidence import main
import run_server_mysql_external_view_read_names


if __name__ == "__main__":
    main(run_server_mysql_external_view_read_names)
