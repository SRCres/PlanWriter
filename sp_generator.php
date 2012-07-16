<?php

$conn = mysql_connect("localhost", "root", "") 
or die ("No se ha podido conectar"); 

mysql_select_db("planwriter", $conn)
or die("Error al tratar de selecccionar la base");

$delimiter = "$$$$";

$tables = array("data_type" => array("data_type_id,SMALLINT(5)", "name,VARCHAR(20)", "description,VARCHAR(256)"),
				"issue" => array("issue_id,INT(10)", "sheet_id,INT(10)"),
				"issue_field" => array("issue_field_id,INT(10)", "data_type_id,SMALLINT(5)", "sheet_id,INT(10)", "name,VARCHAR(128)", "values,VARCHAR(256)", "default_value,VARCHAR(256)"),
				"issue_value" => array("issue_value_id,INT(10)", "issue_field_id,INT(10)", "issue_id,INT(10)", "user_id,SMALLINT(5)", "value,VARCHAR(4096)"),
				"project" => array("project_id,INT(10)", "name,VARCHAR(64)"),
				"sheet" => array("sheet_id,INT(10)", "project_id,INT(10)", "name,VARCHAR(128)"),
				"user" => array("user_id,SMALLINT(5)", "name,VARCHAR(40)", "password,VARCHAR(128)", "email,VARCHAR(256)"),
				"user_project" => array("user_id,SMALLINT(5)", "project_id,INT(10)", "permission,VARCHAR(5)"));

$sp_get_template = "DROP PROCEDURE IF EXISTS get_%table_name% " . $delimiter . "\n\nCREATE PROCEDURE get_%table_name%()\nBEGIN\nSELECT * FROM %table_name%;\nEND " . $delimiter . "\n\n";
$sp_insert_template = "DROP PROCEDURE IF EXISTS insert_%table_name% " . $delimiter . "\n\nCREATE PROCEDURE insert_%table_name%(%params%)\nBEGIN\nIF %primary_key%<=0 THEN\nINSERT INTO %table_name% (%columns_insert%)\nVALUES (%values_insert%);\nEND IF;\nEND " . $delimiter . "\n\n";

function getSPParams($arrayParams, $prefix = "") {
	$sp_params = "";
	for ($i=0; $i<sizeof($arrayParams); $i++) {
		$sp_params .= "IN " . $prefix . str_replace(",", " ", $arrayParams[$i]) . ", ";
	}
	unset($i);
	return substr($sp_params, 0, strlen($sp_params) - 2);
}

function getSPParamName($param_str, $prefix = "") {
	return $prefix . substr($param_str, 0, strrpos($param_str, ","));
}

function getSPColumns($arrayParams, $start_index = 0, $prefix = "") {
	$sp_columns = "";
	for ($i=$start_index; $i<sizeof($arrayParams); $i++) {
		$sp_columns .= "`" . $prefix . getSPParamName($arrayParams[$i]) . "`, ";
	}
	unset($i);
	return substr($sp_columns, 0, strlen($sp_columns) - 2);
}

function buildSPString($table_name, $table_columns, $template_get, $template_insert) {
	// GET
	$get_str = str_replace("%table_name%", $table_name, $template_get);
	// INSERT
	$insert_str = str_replace("%table_name%", $table_name, $template_insert);
	$insert_str = str_replace("%params%", getSPParams($table_columns, "p_"), $insert_str);
	// $start_index=1, salteo la columna id para los insert
	$insert_str = str_replace("%columns_insert%", getSPColumns($table_columns, 1), $insert_str);
	$insert_str = str_replace("%values_insert%", getSPColumns($table_columns, 1, "p_"), $insert_str);
	$insert_str = str_replace("%primary_key%", getSPParamName($table_columns[0], "p_"), $insert_str);
	// UPDATE
	// ...
	return $get_str . $insert_str;
}

function buildAllSPString($delimiter, $tables, $template_get, $template_insert) {
	$str = "DELIMITER " . $delimiter . "\n\n";
	foreach ($tables as $table_name => $table_structure) {
		$str .= "-- TABLE " . $table_name . "\n";
		$str .= buildSPString($table_name, $table_structure, $template_get, $template_insert) . "\n";
	}
	unset($key, $value);
	$str .= "\nDELIMITER ;";
	return $str;
}

function executeMySQLCode($delimiter, $sql_str) {
	$sql_str = str_replace("DELIMITER", "", $sql_str);
	$commands = explode($delimiter, $sql_str);
	for ($i=0; $i<sizeof($commands); $i++) {
		mysql_query($commands[$i]);
	}
}

$sp_query = buildAllSPString($delimiter, $tables, $sp_get_template, $sp_insert_template);
executeMySQLCode($delimiter, $sp_query);
echo str_replace("\n", "<br>", $sp_query);

mysql_close($conn); 

?>
