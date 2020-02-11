<?php
echo "live_dump runing no exit";
//exit;
//$out = shell_exec("mysqldump -usurestep_2 -pN-B_-0SR%3E, surestep_1 > db_dump/surestep_1--".date('Y-m-d-H:i:s').".sql 1>db_dump/success_".date('Y-m-d-H:i:s').".log  2>db_dump/error_".date('Y-m-d-H:i:s').".log");
$out = shell_exec("mysqldump -usurestep_staging -pdjq5!xS~m0v+ surestep_staging > db_dump/surestep_staging--".date('Y-m-d-H:i:s').".sql 2> db_dump/error_".date('Y-m-d-H:i:s').".log");

print_r( $out );
?>
