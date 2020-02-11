<?php
echo "edit fot testing exit with edit";
//exit;
//$out = shell_exec("mysql -usurestep_staging -pdjq5!xS~m0v+ surestep_staging < db_dump/surestep_1.sql");

$out = shell_exec("mysql -usurestep_2 -pN-B_-0SR%3E, surestep_1 < db_dump/surestep_staging--2020-02-06-06:48:01.sql 1> import_success.log 2> import_error.log");
print_r($out);
?>