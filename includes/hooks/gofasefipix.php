<?php
/**
 * Módulo iugu Pix para WHMCS
 * @copyright	2023 Gofas Software
 * @see			https://gofas.net/?p=14950
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14299
 * @version		1.0.0
 */
use WHMCS\Aplication;
$self=App::self();
if(!function_exists('gip_get_protected_property')){
	function gip_get_protected_property($object, $property){
	    $reflectedClass = new \ReflectionClass($object);
	    $reflection = $reflectedClass->getProperty($property);
	    $reflection->setAccessible(true);
	    return $reflection->getValue($object);
	}
}
if( !function_exists('gip_get_string_between') ){
	function gip_get_string_between($string, $start, $end){
		$string = " ".$string;
		$ini = strpos($string,$start);
		if ($ini == 0) return "";
		$ini += strlen($start);   
		$len = strpos($string,$end,$ini) - $ini;
		return substr($string,$ini,$len);
	}
}
$root_dir = '/'.gip_get_string_between(gip_get_protected_property(gip_get_protected_property(gip_get_protected_property(gip_get_protected_property($self,'clientTemplate'),'config'),'configFile'),'path'),'/','/templates/');
require_once $root_dir.'/modules/gateways/gofasiugupix.php';