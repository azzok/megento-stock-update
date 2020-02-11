<?php
/* For clear cache */
//header("Cache-Control: no-cache, must-revalidate");
//header("Expires: Mon, 26 Jul 2020 05:00:00 GMT");
//header("Content-Type: application/xml; charset=utf-8");


/**
 * @author		MagePsycho <info@magepsycho.com>
 * @website		https://www.magepsycho.com
 * @category	Export / Import
 */
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













