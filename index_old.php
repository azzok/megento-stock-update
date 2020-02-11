<?php
/* For clear cache */
//header("Cache-Control: no-cache, must-revalidate");
//header("Expires: Mon, 26 Jul 2020 05:00:00 GMT");
//header("Content-Type: application/xml; charset=utf-8");

$mageFilename = '../app/Mage.php';
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
	$newQty			= $data[1];
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




date_default_timezone_set('Asia/Kolkata'); 
$rawDir = "../var/import/GBSstockfeed.csv";
$dir = glob("../var/import/GBSstockfeed.csv");
//var_dump($dir);
//echo "test2";
//exit;
$db_server = 'localhost';
$db_username = "surestep_2";
$db_password = 'N-B_-0SR%3E,';
$db_database = 'surestep_1';
$db_conn=mysqli_connect($db_server,$db_username,$db_password,$db_database)  or die('error:'.mysqli_connect_error());
if($db_conn){
    echo "connected!";
}
//exit;



foreach($dir as $file) 
{
  if(is_file($file))
  {
  	$inp = file_get_contents('checkModifyDate.json');
	$tempArray = json_decode($inp);
	
	$fileInfo = pathinfo($file);
	$fileName = $fileInfo['filename'];
		if(array_key_exists($fileName,$tempArray)){
			$lastModifyDate = $tempArray->$fileName;
		}else{
		
			echo "Check <b>checkModifyDate.json</b>";
			echo "File is not exists in json<br/>";
			exit;
		}

		//var_dump(pathinfo($file));
    	echo $modDate=date("d-m-Y H:i:s", filemtime($file));
		
		if( $lastModifyDate < $modDate){

			//echo $lastModifyDate ." - File has modified - ".$modDate;
			echo "<h1>File has been modified.</h1><br/>";
			echo "<h3>File Name : ".$fileName."</h1><br/>";
			echo "<h3>Last Modify Date : ".$lastModifyDate."</h1><br/>";
			echo "<h3>Recently Modify Date : ".$modDate."</h1><br/>";
			$tempArray->$fileName = $modDate;
			$jsonData = json_encode($tempArray);
			file_put_contents('checkModifyDate.json', $jsonData);
			
			/// Update functionality start
            $csv				= new Varien_File_Csv();
            $data				= $csv->getData('../var/import/GBSstockfeed.csv'); //path to csv
            array_shift($data);
            echo "update SKU";
            //var_dump(array_shift($data));
            echo "</hr>";
            //exit;
            $message = '';
            $count   = 1;
            foreach($data as $_data){
            	if(_checkIfSkuExists($_data[0])){
            	   
            	    $db_and_csv_stock = _checkIfSkuQtySameOrNot($_data[0],$_data[3]);
            	    echo "Product SKU : ".$_data[0]." - CSV QTY : ".$db_and_csv_stock['csv_stock']." DB QTY : ".$db_and_csv_stock['dp_stock'];
            	    echo "\n";
            	    //exit;
            	    if($db_and_csv_stock['dp_stock'] != $db_and_csv_stock['csv_stock']){
                    	   try{
                    			//_updateStocks($_data);
                    			$message .= $count . '> Success:: Qty (' . $_data[1] . ') of Sku (' . $_data[0] . ') has been updated. <br />';
                    			
                    
                    		}catch(Exception $e){
                    			$message .=  $count .'> Error:: while Upating  Qty (' . $_data[1] . ') of Sku (' . $_data[0] . ') => '.$e->getMessage().'<br />';
                    		}
            	    
                    }else{
                        
                    }
            		
            		
            	}else{
            		$message .=  $count .'> Error:: Product with Sku (' . $_data[0] . ') does\'t exist.<br />';
            	}
            	$count++;
            }
            //echo $message;
            
            //Update functionality end
			
			$myfile = file_put_contents('dateModifyLog.log',  "File Name  : ".$file." - Last Modify Date : ".$lastModifyDate."- Recently Modify Date  :".$modDate." - Date : ".date('Y-m-d H:i:s').PHP_EOL , FILE_APPEND | LOCK_EX);
			
			


		}else{
			echo "<h1>File is not modified.</h1><br/>";
			
			$file = fopen($rawDir, 'r');
            while (($line = fgetcsv($file)) !== FALSE) {
               //$line[0] = '1004000018' in first iteration
              
               print_r($line);
               echo "<br/>";
               
               //exit;
            }
            fclose($file);


		}

	

    //echo "<br>$file last modified on ". $mod_date;
  }
  else
  {
    echo "<br>$file is not a correct file";
  }
}

?>