<?php
/**
 * Módulo Efí Boleto para WHMCS
 * @copyright	2024 Gofas Software
 * @see			https://gofas.net/?p=15590
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14299
 * @version		4.0.0
 */
if((int)substr(preg_replace('/[^\da-z]/i','',phpversion()),0,2)>=(int)81){
	require_once __DIR__.'/gofasefiboleto/index.php';
}
if((int)substr(preg_replace('/[^\da-z]/i','',phpversion()),0,2)<=(int)74){
    require_once __DIR__.'/gofasefiboleto/indexd.php';
}