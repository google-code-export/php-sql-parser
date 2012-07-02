<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

// not solved
$sql = "SELECT CAST((CONCAT(table1.col1,' ',time_start)) AS DATETIME) FROM table1";
$parser = new PHPSQLParser($sql);
$p = $parser->parsed;
print_r($p);
$expected = getExpectedValue(dirname(__FILE__), 'issue62.serialized');
eq_array($p, $expected, 'CAST expression');
