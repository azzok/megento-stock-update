#!/bin/sh
cd /home/surestep/public_html/checkFileLastModify/cron/

datevar=`date +"%m-%d-%Y_%H:%M:%S"`
/usr/local/bin/ea-php56 -q  /home/surestep/public_html/checkFileLastModify/index.php 1> /home/surestep/public_html/checkFileLastModify/log/cron_log/$datevar.txt 2> /home/surestep/public_html/checkFileLastModify/log/cron_log/$datevar.err.txt