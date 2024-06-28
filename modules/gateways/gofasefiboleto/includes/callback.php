<?php
/**
 * Módulo Efí Boleto para WHMCS
 * @copyright	2023 Gofas Software
 * @see			https://gofas.net/?p=7893
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=7856
 * @version		4.0.0
 */

require_once __DIR__ . '/../../../../../init.php';
require_once __DIR__ . '/../../../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../index.php';
use WHMCS\Database\Capsule;
use WHMCS\Aplication;

if($_POST['id']){
	$params = getGatewayVariables('gofasefiboleto');
	$params_api = gefib_api_connect();
	$invoice = localAPI('getinvoice',array('invoiceid'=> $_POST['id']),(int)gefib_setup_admin()['id']);
	if( $invoice['invoiceid']){
		$qrcode = gefib_get_local_qrc($_POST['id']);	
		$charge = gefib_charge_verify($qrcode['txid']);
		if(((STRING)$charge['result']['status'] === (STRING)'CONCLUIDA') and $invoice['status'] !== 'Paid' and (float)$invoice['total'] === (float)$charge['result']['valor']['original']){
			$add_trans = gefib_add_trans($invoice['userid'],$_POST['id'], (float)$charge['result']['valor']['original'], gefib_fee($charge['result']['valor']['original']), 'gefib-'.$params_api['api_mode'].'-'.$qrcode['txid'], 'Boleto pago - confirmação via webhook /includes/pix/');			
		}
		if($charge['result']['status']){
			echo $charge['result']['status'];
		}
	}
	if($params['log']){
		logModuleCall('gofasefiboleto','post_2',array('request'=>$_POST),'', array( 'charge'=>$charge ) );
	}
}
//echo '<pre>',(float)'100.00'/100*(float)'0.99','</pre>';