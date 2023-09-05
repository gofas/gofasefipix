<?php
/**
 * Módulo Gofas Efi Pix para WHMCS
 * @copyright	2023 Gofas Software
 * @see			https://gofas.net/?p=15590
 * @license		https://gofas.net/?p=9340
 * @version		1.0.0
 */
if((int)substr(preg_replace('/[^\da-z]/i','',phpversion()),0,2)>=(int)81){
	require_once __DIR__.'/gofasefipix/index.php';
}
if((int)substr(preg_replace('/[^\da-z]/i','',phpversion()),0,2)<=(int)74){
    require_once __DIR__.'/gofasefipix/indexd.php';
}