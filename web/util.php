<?php

function make_db_string() {
	return json_decode(file_get_contents("db.json"));
}

function getQueryPlan($conn, $query) {
	// This functions prepends "EXPLAIN " To a query and returns the results

	$result = pg_query("EXPLAIN ".$query);

	$arr = pg_fetch_all($result);

	return $arr;
}

?>
