<?php
/**
 * Módulo Gofas Efi Pix para WHMCS
 * @copyright	2023 Gofas Software
 * @see			https://gofas.net/?p=15590
 * @license		https://gofas.net/?p=9340
 * @version		1.0.0
 */

// Use $opt_num++ para enumerar as opções
// Não renomear a variável $gefip_custom_config

$gefip_custom_config_ = [ 
	// Opção 1
	'optiontest' => [ // Utilize $params['optiontest'] no arquivo /custom/params.php para incluir o valor dessa opção na função gofasefipix_link
		'FriendlyName' => $opt_num++.'- Configuração personalizada',
		'Type' => 'text',
		'Size' => '40',
		'Default' => 'Valor padrão',
		'Description' => 'Campo de configuração via arquivo /custom/config.php',
	],
	// Opção 2
	'optiontest2' => [
		'FriendlyName' => $opt_num++.'- Outra configuração personalizada',
		'Type' => 'text',
		'Size' => '40',
		'Default' => '',
		'Description' => 'Customização via arquivo /custom/config.php',
	],
];
//$gefip_custom_config = $gefip_custom_config_;