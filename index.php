<?php
date_default_timezone_set('Asia/Kolkata'); 
$flag = true;
//if(isset($_GET['flag'])){
    
// if(isset($_GET) && $_GET['flag']=="true") {
//     echo "<h3>Flag : True</h3><br/>";
//     echo "<h3>Update in DB</h3>";
//     $flag=true;
// }else{
//      echo "<h3>Flag : False</h3><br/>";
//      echo "<h3>Show Only ther is NO Update in DB</h3>";
// }
echo "<h5>CRON LOG - ".date('Y-m-d H:i:s')."</h5>\n";

$myfile = file_put_contents('/home/surestep/public_html/checkFileLastModify/log/cron.log',  "Last RUN - Date : ".date('Y-m-d H:i:s').PHP_EOL , FILE_APPEND | LOCK_EX);

/* For clear cache */
// header("Expires: Tue, 01 Jan 2000 00:00:00 GMT"); 
// header("Last-Modified: " . date("D, d M Y H:i:s") . " GMT"); 
// header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); 
// header("Cache-Control: post-check=0, pre-check=0", false); 
// header("Pragma: no-cache");

clearstatcache();

//$mageFilename = '../app/Mage.php';
$mageFilename = '/home/surestep/public_html/app/Mage.php';

require_once $mageFilename;
Mage::setIsDeveloperMode(true);
ini_set('display_errors', 1);
umask(0);
Mage::app('admin');
Mage::register('isSecureArea', 1);
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

set_time_limit(0);
ini_set('memory_limit','1024M');


/***************** UTILITY FUNCTIONS ********************/
function _getConnection($type = 'core_read'){
	return Mage::getSingleton('core/resource')->getConnection($type);
}

function _getTableName($tableName){
	return Mage::getSingleton('core/resource')->getTableName($tableName);
}

function _getAttributeId($attribute_code = 'price'){
	$connection = _getConnection('core_read');
	$sql = "SELECT attribute_id
				FROM " . _getTableName('eav_attribute') . "
			WHERE
				entity_type_id = ?
				AND attribute_code = ?";
	$entity_type_id = _getEntityTypeId();
	return $connection->fetchOne($sql, array($entity_type_id, $attribute_code));
}

function _getEntityTypeId($entity_type_code = 'catalog_product'){
	$connection = _getConnection('core_read');
	$sql		= "SELECT entity_type_id FROM " . _getTableName('eav_entity_type') . " WHERE entity_type_code = ?";
	return $connection->fetchOne($sql, array($entity_type_code));
}

function _checkIfSkuExists($sku){
	$connection = _getConnection('core_read');
	$sql		= "SELECT COUNT(*) AS count_no FROM " . _getTableName('catalog_product_entity') . "	WHERE sku = ?";
	$count		= $connection->fetchOne($sql, array($sku));
	if($count > 0){
		return true;
	}else{
		return false;
	}
}

function _checkIfSkuQtySameOrNot($sku,$qty){
        $result = array();
	    //$connection = _getConnection('core_read');
        // $_product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
        // $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product);
        // Zend_Debug::dump($stock->getData());
        
        // echo "qty - ".$stock->getQty();echo "\n";
        // echo $stock->getMinQty();echo "\n";
        // echo $stock->getMinSaleQty();echo "\n";
            
		        $model = Mage::getModel('catalog/product'); 
                $productId		= _getIdFromSku($sku);
                $_product = $model->load($productId); 
                $stocklevel = (int)Mage::getModel('cataloginventory/stock_item')
                    ->loadByProduct($_product)->getQty();
                    $result['dp_stock']=$stocklevel;
                    $result['csv_stock']=$qty;
                 //$result = array('dp_stock'=>$stocklevel,'csv_stock'=>$qty);
                return  $result;

	
}

function _getIdFromSku($sku){
	$connection = _getConnection('core_read');
	$sql		= "SELECT entity_id FROM " . _getTableName('catalog_product_entity') . " WHERE sku = ?";
	return $connection->fetchOne($sql, array($sku));
}

function _updateStocks($data){
	$connection		= _getConnection('core_write');
	$sku			= $data[0];
	$newQty			= $data[3];
	
        // $model = Mage::getModel('catalog/product'); 
        // echo 'productId - '.$productId		= _getIdFromSku($sku);
        // //$productId = 169;
        // $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
        // if ($stockItem->getId() > 0 and $stockItem->getManageStock()) {
        // 	$qty = 100;
        // 	$stockItem->setQty($newQty);
        // 	$stockItem->setIsInStock((int)($newQty > 0));
        // 	$stockItem->save();
        // }
        

	
    	$productId		= _getIdFromSku($sku);
    	$attributeId	= _getAttributeId();
    
    	$sql			= "UPDATE " . _getTableName('cataloginventory_stock_item') . " csi,
    					   " . _getTableName('cataloginventory_stock_status') . " css
    	                   SET
    					   csi.qty = ?,
    					   csi.is_in_stock = ?,
    	                   css.qty = ?,
    					   css.stock_status = ?
    					   WHERE
    					   csi.product_id = ?
    			           AND csi.product_id = css.product_id";
    	$isInStock		= $newQty > 0 ? 1 : 0;
    	$stockStatus	= $newQty > 0 ? 1 : 0;
    	$connection->query($sql, array($newQty, $isInStock, $newQty, $stockStatus, $productId));



}
/***************** UTILITY FUNCTIONS ********************/



echo "<h4>File Name : GBSstockfeed</h4>\n";
$rawDir = "/home/surestep/public_html/var/import/GBSstockfeed.csv";
$dir = glob("/home/surestep/public_html/var/import/GBSstockfeed.csv");
//var_dump($dir);
echo "<h5>File Path :".$rawDir."</h5>\n";

foreach($dir as $file) 
{
  if(is_file($file))
  {
  	$inp = file_get_contents('/home/surestep/public_html/checkFileLastModify/checkModifyDate.json');
	$tempArray = json_decode($inp);
	
	$fileInfo = pathinfo($file);
	$fileName = $fileInfo['filename'];
		if(array_key_exists($fileName,$tempArray)){
			$lastModifyDate = $tempArray->$fileName;
		}else{
		
			echo "Check <b>checkModifyDate.json</b>\n";
			echo "File is not exists in json<br/>\n";
			exit;
		}

		//var_dump(pathinfo($file));
    	echo "Run Time : ".$modDate=date("d-m-Y H:i:s", filemtime($file))." \n";
		
		//if( $lastModifyDate < $modDate){
		//if( $lastModifyDate > $modDate){
        //if( $lastModifyDate == $modDate){
        if( $lastModifyDate != $modDate){
		
        	 //echo $lastModifyDate ." - File has modified - ".$modDate;
			echo "<h1>File has been modified.</h1><br/>\n";
			echo "<h3>File Name : ".$fileName."</h1><br/>\n";
			echo "<h3>Last Modify Date : ".$lastModifyDate."</h1><br/>\n";
			echo "<h3>Recently Modify Date : ".$modDate."</h1><br/>\n";
			$tempArray->$fileName = $modDate;
			$jsonData = json_encode($tempArray);
			file_put_contents('/home/surestep/public_html/checkFileLastModify/checkModifyDate.json', $jsonData);
			$myfile = file_put_contents('/home/surestep/public_html/checkFileLastModify/log/dateModifyLog.log',  "File Name  : ".$file." - Last Modify Date : ".$lastModifyDate."- Recently Modify Date  :".$modDate." - Date : ".date('Y-m-d H:i:s').PHP_EOL , FILE_APPEND | LOCK_EX);
			
			/// Update functionality start
            $csv				= new Varien_File_Csv();
            $data				= $csv->getData('/home/surestep/public_html/var/import/GBSstockfeed.csv'); //path to csv
            array_shift($data);
            echo "Live update SKU\n";
            //var_dump(array_shift($data));
            echo "</hr>\n";
            //exit;
            $message = '';
            $count   = 1;
            foreach($data as $_data){
            	if(_checkIfSkuExists($_data[0])){
            	   
            	    $db_and_csv_stock = _checkIfSkuQtySameOrNot($_data[0],$_data[3]);
            	   
            	    //if($count == 3 )exit;  
            	        //if (strpos($_data[0], 'ALDERSHOT') !== false) {
                            //echo 'true';  
                             
                    	    if($db_and_csv_stock['dp_stock'] != $db_and_csv_stock['csv_stock']){
                    	        
                            	   try{
                            	       if($flag){
                            	        _updateStocks($_data);
                            			$message .= $count . '> Success:: Qty (' . $_data[1] . ') of Sku (' . $_data[0] . ') has been updated. <br />';
                            			echo "Product SKU  : ".$_data[0]." - <b>CSV QTY</b> : ".$db_and_csv_stock['csv_stock']." <b>DB QTY</b> : ".$db_and_csv_stock['dp_stock']." - is_in_stock  : ".$_data[4]." <br/>\n";
            	                        echo "<br/>\n";
                            			$myfile = file_put_contents('/home/surestep/public_html/checkFileLastModify/log/dataUpdateSuccess.log',  "Product SKU : ".$_data[0]." - <b>CSV QTY</b> : ".$db_and_csv_stock['csv_stock']." <b>DB QTY</b> : ".$db_and_csv_stock['dp_stock']."  - is_in_stock  : ".$_data[4]." - Date : ".date('Y-m-d H:i:s').PHP_EOL , FILE_APPEND | LOCK_EX);
                            	       }else{
                            			$message .= $count . '> View :: Qty (' . $_data[1] . ') of Sku (' . $_data[0] . ') <br />';
                            			echo "View :: Product SKU  : ".$_data[0]." - <b>CSV QTY</b> : ".$db_and_csv_stock['csv_stock']." <b>DB QTY</b> : ".$db_and_csv_stock['dp_stock']." - is_in_stock  : ".$_data[4]." <br/>\n";
            	                        echo "<br/>\n";
                            			$myfile = file_put_contents('/home/surestep/public_html/checkFileLastModify/log/dataUpdateSuccess.log',  "Product SKU : ".$_data[0]." - <b>CSV QTY</b> : ".$db_and_csv_stock['csv_stock']." <b>DB QTY</b> : ".$db_and_csv_stock['dp_stock']." -  is_in_stock  : ".$_data[4]." - Date : ".date('Y-m-d H:i:s').PHP_EOL , FILE_APPEND | LOCK_EX);
                            
                            	       }

                            		}catch(Exception $e){
                            		    echo "Live ERROR Product SKU  : ".$_data[0]." - <b>CSV QTY</b> : ".$db_and_csv_stock['csv_stock']." <b>DB QTY</b> : ".$db_and_csv_stock['dp_stock']."\n";
            	                        echo "<br/>\n";
                            			$message .=  $count .'> Error:: while Upating  Qty (' . $_data[1] . ') of Sku (' . $_data[0] . ') => '.$e->getMessage().'<br />\n';
                            		    $myfile = file_put_contents('/home/surestep/public_html/checkFileLastModify/log/dataUpdateError.log',  "Product SKU : ".$_data[0]." - <b>CSV QTY</b> : ".$db_and_csv_stock['csv_stock']." <b>DB QTY</b> : ".$db_and_csv_stock['dp_stock']." - Date : ".date('Y-m-d H:i:s').PHP_EOL , FILE_APPEND | LOCK_EX);
                            
                            		}
                    	    
                            }else{
                                //echo "NOT CHANGE<br/>";
                                echo "NOT CHANGE -  Product SKU : ".$_data[0]." - <b>CSV QTY</b> : ".$db_and_csv_stock['csv_stock']." <b>DB QTY</b> : ".$db_and_csv_stock['dp_stock']."<br />\n";
                                $myfile = file_put_contents('/home/surestep/public_html/checkFileLastModify/log/dataNotChange.log',  "Product SKU : ".$_data[0]." - <b>CSV QTY</b> : ".$db_and_csv_stock['csv_stock']." <b>DB QTY</b> : ".$db_and_csv_stock['dp_stock']." - Date : ".date('Y-m-d H:i:s').PHP_EOL , FILE_APPEND | LOCK_EX);
        
                            }
            	       // }
            	
            	}else{
            		$message .=  $count .'> Error:: Product with Sku (' . $_data[0] . ') does\'t exist.<br />\n';
            		$myfile = file_put_contents('/home/surestep/public_html/checkFileLastModify/log/notExisitSKU.log',  "Product SKU : ".$_data[0]." - <b>CSV QTY</b> : ".$db_and_csv_stock['csv_stock']." <b>DB QTY</b> : ".$db_and_csv_stock['dp_stock']." - Date : ".date('Y-m-d H:i:s').PHP_EOL , FILE_APPEND | LOCK_EX);
            	}
            	$count++;
            }
            //Update functionality end

		}else{
			echo "<h1>File is not modified.</h1><br/>\n";
			$myfile = file_put_contents('/home/surestep/public_html/checkFileLastModify/log/notModifiy.log',  "Last RUN - Date : ".date('Y-m-d H:i:s').PHP_EOL , FILE_APPEND | LOCK_EX);
                    
		}

	

    //echo "<br>$file last modified on ". $mod_date;
  }
  else
  {
    echo "<br>$file is not a correct file\n";
  }
}
// }else{
//     echo "<h2>There is no flag.</h2><br/><b>Use following query string:</b><br/> <b>1.Update db</b><pre><code>?flag=true</code></pre><br/><b>2. Only View changes</b><pre><code>?flag=false</code></pre>";
// }
?>