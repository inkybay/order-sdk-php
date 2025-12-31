<?php

	
	require_once('conf.php');


	//	Get an order item details
	
	//	Param 1 : Order Id : You will get it from where your hook url pointed. and will get it by $_GET['orderId'] 
	//	Param 2 : Item Id : You will get it from where your hook url pointed. and will get it by $_GET['itemId']
	//	Param 3 : Output format 'json' or 'xml'
	$data = $orderService->getOrderItem($_GET['orderId'],$_GET['itemId'],'json');

	

	//print_r($data);
	$dataJson = json_decode($data);

	if(isset($dataJson->order->Error)) {
		echo "Error: " . $dataJson->order->Error;
		exit;
	}



	//	Print Output

	//For xml
	//header('Content-type: text/xml');
	//echo $data;
	
	//For json
	//header('Content-type: application/json');
	//echo $data;

	$oderId = $_GET['orderId'];
	$itemId = $_GET['itemId'];
	//file_put_contents(dirname(__FILE__).'/orders/'.$_GET['orderId'].'_'.$_GET['itemId'].'.json', $data);

	// $ordDir = dirname(__FILE__) . '/orders/' . $orderId;
	// if (!is_dir($ordDir)) {
	// 	mkdir($ordDir, 0755, true);
	// }

	// $itmDir = $ordDir. '/' . $itemId;
	// if (!is_dir($itmDir)) {
	// 	mkdir($itmDir, 0755, true);
	// }

	include_once __DIR__ . '/extract.php';
	$extractor = new Extract(__DIR__ . '/orders', $data, $itemId);
	$result = $extractor->run();

	// print_r($result);


?>