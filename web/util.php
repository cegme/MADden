<?php

function make_db_string() {
	return json_decode(file_get_contents("db.json"));
}

?>
