<?php
/**
 * Módulo Gofas Efi Pix para WHMCS
 * @copyright	2023 Gofas Software
 * @see			https://gofas.net/?p=15590
 * @license		https://gofas.net/?p=9340
 * @version		1.0.0
 */
use WHMCS\Database\Capsule;
use WHMCS\Aplication;
if(!function_exists('gefip_whmcs_url')){
	function gefip_whmcs_url($type='all'){
        $info=[];
        $self = App::self();
		$info['root_dir'] = '/'.gefip_get_string_between(gefip_get_protected_property(gefip_get_protected_property(gefip_get_protected_property(gefip_get_protected_property($self, 'clientTemplate'), 'config'),'configFile'),'path'),'/','/templates/');
		$info['whmcs_url'] = App::getSystemUrl();
		$info['admin_path'] = gefip_get_protected_property($self, 'customadminpath');
        $info['admin_url'] = $info['whmcs_url'].$info['admin_path'];
		if((string)$type===(string)'all'){
			return $info;
		}
        return $info[$type];
	}
}
if(!function_exists('gefip_api_connect')){
	function gefip_api_connect(){
		$params = getGatewayVariables('gofasefipix');
		if($params['sandbox']){
			$params_api = [
				'api_mode' => 'sandbox',
				'api_token' => $params['sandbox_api_token'],
				'charge_url' => 'https://api.efi.com/v1',
			];
		}
		if(!$params['sandbox']){
			$params_api = [
				'api_mode' => 'live',
				'api_token' => $params['api_token'],
				'charge_url' => 'https://api.efi.com/v1',
			];
		}
		return $params_api;
	}
}
if(!function_exists('gefip_enable_pix')){
	function gefip_enable_pix(){
		$params = getGatewayVariables('gofasefipix');
		if(!$params['api_token']){
			return;
		}
		$params_api = gefip_api_connect();
		foreach( Capsule::table('tblconfiguration')->where('setting','=','gefip_pix_enabled')->get(['value','created_at','updated_at']) as $pix_enabled_ ){
			$pix_enabled	= $pix_enabled_->value;
			$created_at		= $pix_enabled_->created_at;
			$updated_at		= $pix_enabled_->updated_at;	
		}
		if($pix_enabled and (string)$pix_enabled === (string)'enabled'){
			return;
		}
		if(($pix_enabled and (string)$pix_enabled !== (string)'enabled') || !$pix_enabled){
    		$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => $params_api['charge_url'].'/payments/pix',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_HTTPHEADER => array(
			    	'Authorization: Basic '.base64_encode((string)$params['api_token'].':'),
					'Content-Type: application/json',
					'Accept: application/json',
			  	),
				CURLOPT_CUSTOMREQUEST => 'PUT',
				CURLOPT_POSTFIELDS => json_encode(['enable'=>true]),
			));
			$result = json_decode(curl_exec($curl),true);
			$result_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			curl_close($curl);
			if(is_array($result)){
			if((int)$result_code === (int)200 || ((int)$result_code !== (int)200 and $result['errors']['base']['0'] and $result['errors']['base']['0'] === 'Conta já possui Pix ativo')){
				if(!$pix_enabled){
					try { Capsule::table('tblconfiguration')->insert(array(
						'setting' => 'gefip_pix_enabled',
						'value' => 'enabled',
						'created_at' => date("Y-m-d H:i:s"),
						'updated_at' => date("Y-m-d H:i:s")
					));
					}
					catch (\Exception $e){
						$error .= $e->getMessage();
					}
				}
				if($pix_enabled){
					try { Capsule::table('tblconfiguration')->update(array(
						'setting' => 'gefip_pix_enabled',
						'value' => 'enabled',
						'created_at' => $created_at,
						'updated_at' => date("Y-m-d H:i:s")
					));
					}
					catch (\Exception $e){
						$error .= $e->getMessage();
					}
				}
			}
			else{
				logActivity('Gofas efi - Pix | Erro ao ativar pagamento via PIX na conta efi: '.$result_code.': '.$result,0);
			}
			}
		}
		if($error){
			return $error;
		}
		return;
	}
}
if(!function_exists('gefip_verify_install')){
	function gefip_verify_install(){
		if(!Capsule::schema()->hasTable('gofasefipix') ){
			try {
				Capsule::schema()->create('gofasefipix', function($table){
					$table->string('invoice_id');
					$table->string('charge_id');
					$table->string('amount');
					$table->string('duedate');
					$table->text('qrcode');
					$table->string('qrcode_text');
					$table->string('api_mode');
					$table->string('created_at');
					$table->string('updated_at');
				});
			}
			catch (\Exception $e){
				$error .= "Não foi possível criar a tabela do módulo no banco de dados: {$e->getMessage()}";
			}
		}
		if(!$error){
			return array('sucess'=>1);
		}
		elseif($error){
			return array('error'=>$error);
		}
	}
}
if(!function_exists('gefip_get_embed')){
	function gefip_get_embed($page_id,$referer,$module_version){
		$query = 'https://gofas.net/cliente/gofas/updates/?embed='.$page_id.'&referer='.$referer.'&version='.$module_version;
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl, CURLOPT_URL, $query);
		$embed = curl_exec($curl);
		$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return ['embed'=>$embed,'http_code'=>$http_status];
	}
}
if(!function_exists('gefip_encrypt')){
	function gefip_encrypt($q) {
	    $encryptionMethod = "AES-256-CBC";
		$secretHash = "535ba9979bc6c7ff151f2136cd13b0f9";
	    return openssl_encrypt($q, $encryptionMethod, $secretHash);
	}
}
if(!function_exists('gefip_decrypt')){
	function gefip_decrypt($q){
		$encryptionMethod = "AES-256-CBC";
		$secretHash = "535ba9979bc6c7ff151f2136cd13b0f9";
	    return openssl_decrypt($q, $encryptionMethod, $secretHash);
	}
}
if(!function_exists('gefip_get_version')){
	function gefip_get_version($page_id,$referer,$module_version){
		$current_admin = gefip_current_admin();
		$query = '?software_id='.$page_id.'&install_url='.$referer.'&current_version='.$module_version.'&installer_email='.$current_admin['email'].'&installer_firstname='.$current_admin['firstname'].'&installer_lastname='.$current_admin['lastname'].'&action=verify'.gefip_sysinfo();
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl, CURLOPT_URL, 'https://gofas.net/br/updates/stats.php'.$query);
		$available_version_ = curl_exec($curl);
		$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return ['version'=>$available_version_,'http_code'=>$http_status];
	}
}
if(!function_exists('gofasefipix_config')){
    function gofasefipix_config(){
		$gefip_config = [];
    	if(stripos($_SERVER['REQUEST_URI'], 'configgateways')!==false){
    		$module_version	= '1.0.0';
    		$module_page	= '15590';
            $verify_install = gefip_verify_install();
    		$whmcs_url = gefip_whmcs_url();
    		$check_updates = gefip_verify_module_updates($module_page,$whmcs_url['admin_url'],$module_version);
    		if($_REQUEST['resetversion'] === 'gofasefipix'){
                gefip_reset_local_version();
                header_remove();
    			header("Location: ".$whmcs_url['admin_url'].'/configgateways.php?manage=gofasefipix#m_gofasefipix',true,303);
    			exit;
            }
    		foreach( Capsule::table('tblconfiguration')
    		->where('setting','=','Version')
    		->get(['value']) as $data1 ){
    			$Version = $data1->value;
    		}
    		$whmcs_version=(int)preg_replace('/[^\da-z]/i', '',  gefip_get_string_between('#'.$Version, '#', '-'));
    		if($whmcs_version<861){
    			return [
    				'FriendlyName' => [
    					'Type' => 'System',
    					'Value' => 'Gofas efi - Pix',
    				],
    				'separator_1' => [
    					'Description' => '
    					<div>
    						<div style="float: right; padding: 0px;">
    						'.gefip_decrypt($check_updates['check']).'
    						</div>
    						<div>
    							<h4 style="padding-top: 5px; color: red;">Módulo Gofas efi - Pix para WHMCS v'.$module_version.' | requer WHMCS versão 8.6.1 ou superior</h4>
    							'.$check_updates['message'].'
    							<p><a style="text-decoration:underline;" target="_blank" href="https://gofas.net/?p=15590#configuration">Documentação do módulo</a> | <a style="text-decoration:underline;" target="_blank" href="https://dev.efi.com/reference/metadados/">Documentação da API efi</a></p>
								'.gefip_file_exists_check('/includes/hooks/gofasefipix.php').'
							</div>
    					</div>',
    				],
    				'footer' => [
    					'Description' => '<div class="gefip_section">
    					<p>&copy; '.date('Y').' <a style="text-decoration:underline;" target="_blank" title="↗ Gofas.net" href="https://gofas.net">Gofas.net</a> | <a style="text-decoration:underline;" target="_blank" title="↗ Gofas.net" href="https://gofas.net/?p='.$module_page.'#changelog">'.$module_version.'</a> | <a  style="text-decoration:underline;"target="_blank" title="↗ Documentação" href="https://gofas.net/?p='.$module_page.'">Documentação</a> | <a style="text-decoration:underline;" target="_blank" title="↗ Fórum de Suporte" href="https://gofas.net/foruns/">Suporte</a>.</p>
    					<p style="font-size: 11px;">
    					Ao utilizar esse módulo você concorda com nosso <a style="text-decoration:underline;" target="_blank" title="↗ Contrato de licença de uso de software" href="https://gofas.net/?p=9340">contrato de licença de uso de software</a>.
    					</p>
    					'.$check_updates['message'].'
    					</div>',
    				],
    			];
    		}
    		$opt_num = 1;
    		$renderize = array(
    			'FriendlyName' => array(
    				'Type' => 'System',
    				'Value' => 'Gofas efi - Pix',
    			),
    			'separator_1' => array(
    				'Description' => '
    				<div>
    					<div style="float: right; padding: 0px;">
    					'.gefip_decrypt($check_updates['check']).'
    					</div>
    					<div>
    						<h4 style="padding-top: 5px;">Módulo Gofas efi - Pix para WHMCS v'.$module_version.'</h4>
    						'.$check_updates['message'].'
    						<p><a style="text-decoration:underline;" target="_blank" href="https://gofas.net/?p=15590#configuration">Documentação do módulo</a> | <a style="text-decoration:underline;" target="_blank" href="https://dev.efi.com/reference/metadados/">Documentação da API efi</a></p>
							'.gefip_file_exists_check('/includes/hooks/gofasefipix.php').'
    					</div>
    				</div>',
    			),
    			'api_token' => array(
    				'FriendlyName' => $opt_num++.'- API token produção<span class="gefip_required">*</span>',
    				'Type' => 'password',
    				'Size' => '50',
    				'Default' => '',
    				'Description' => '<a target="_blank" style="text-decoration:underline;" href="https://alia.efi.com/settings/account/api_integration">Obter API token</a>',
    			),
				'sandbox_api_token' => array(
    				'FriendlyName' => $opt_num++.'- API token teste<span class="gefip_required">*</span>',
    				'Type' => 'password',
    				'Size' => '50',
    				'Default' => '',
    				'Description' => '<a target="_blank" style="text-decoration:underline;" href="https://alia.efi.com/settings/account/api_integration">Obter API token</a>',
    			),
    			'separator_2' => array(
    				'Description' => '<span><a target="_blank" style="text-decoration:underline;" href="https://dev.efi.com/reference/autentica%C3%A7%C3%A3o#criando-suas-chaves-de-api-api-tokens-via-painel">Veja aqui como criar suas chaves de API (API Tokens) via painel efi</a></span>',
				),
				// Sandbox
    			'sandbox' => array(
    				'FriendlyName' => $opt_num++.'- <i>Sandbox</i>',
    				'Type' => 'yesno',
    				'Default' => 'yes',
    				'Description' => 'Ative essa opção para gerar cobranças em modo de teste.',
    			),
    			// Log
    			'log' => array(
    				'FriendlyName' => $opt_num++.'- Salvar Logs',
    				'Type' => 'yesno',
    				'Default' => 'yes',
    				'Description' => 'Salva informações de diagnóstico em <a target="_blank" style="text-decoration: underline;" href="'.$whmcs_url['admin_url'].'/systemmodulelog.php">Utilitários > Logs > Log de Módulo</a>. Para funcionar, antes é necessário ativar o debug de módulo clicando em "Ativar Log de Debug". <a target="_blank" style="text-decoration: underline;" href="'.$whmcs_url['admin_url'].'/systemmodulelog.php">VER LOG</a>.',
    			),
    			// minimum amount
    			'minimunamount' => array(
    				'FriendlyName' => $opt_num++.'- Valor mínimo',
    				'Type' => 'text',
    				'Size' => '10',
    				'Default' => '5',
    				'Description' => 'Insira o valor total mínimo da fatura para permitir pagamento via Pix. Formato: Decimal, separado por ponto. Não deve ser menor que o valor da tarifa aplicada à sua conta efi.',
    			),
    			// Dias + vencimento
    			'diasparavencimento' => array(
            	    'FriendlyName'      => $opt_num++.'- Dias até o vencimento',
            	    'Type'              => 'text',
    				'Size'				=> '10',
    				'Default' 			=> '2',
            	    'Description'       => 'Dias entre a data de emissão e a data do vencimento do qrcode quando gerado no dia do vencimento ou após o vencimento da fatura. Pix gerado antes do vencimento da fatura é emitido com a mesma data de vencimento da fatura. Mínimo 1 máximo 30.',
            	),
    			// Top billet button message 
    			'message' => array(
    				'FriendlyName' => $opt_num++.'- Mensagem na fatura',
    				'Type' => 'text',
    				'Size' => '50',
    				'Default' => 'QR Code gerado com sucesso.<br>Escaneie ou copie e cole o QR code.<br>',
    				'Description' => 'Texto exibido na fatura acima do botão "Vizualizar Pix"',
    			),
    			'separator_3' => array(
    				'Description' => '<b>Confirmação automática de pagamentos</b> - <a href="https://gofas.net/gefip/#autoverifypayments" target="_blank" style="text-decoration: underline;">Entenda como funciona</a> &#10138;
                    <br>'.gefip_file_exists_check('/includes/hooks/gofasefipix.php',NULL,$error_msg='<span style="color: red;border-left: 2px solid red;padding-left: 5px;">Atenção! Hook não instalado. Para esse recurso funcionar você deve <b>instalar o hook que acompanha o módulo</b> - <a style="text-decoration:underline;color:red" target="_blank" href="https://gofas.net/gefip/#instalation">Saiba mais </a>&#10138;.</span>').'
                    ',
                ),
    			// Horário da verificação
    			'verifypaymentsat' => array(
            	    'FriendlyName'      => $opt_num++.'- Horário da verificação',
            	    'Type'              => 'text',
    				'Size'				=> '2',
    				'Default' 			=> '05:00',
              		'Description'       => 'Horário em que módulo deve verificar o status de pagamento dos QR codes e confirmar o pagamento das faturas. Formato: HH:MM',
            	),
    			// Horário da verificação
    			'maxinvoicespercheck' => array(
            	    'FriendlyName'      => $opt_num++.'- Verificações por requisição',
            	    'Type'              => 'text',
    				'Size'				=> '2',
    				'Default' 			=> '100',
            	    'Description'       => 'Número máximo de transações consultadas por vez. As consultas à API efi são realizadas em fila onde todas as faturas a verificar são divididas em lotes, cuja quantidade é o valor definido nesse campo.',
            	),
    		);
    		$footer = array('footer' => array(
    				'Description' => '<div class="gefip_section">
    				<p>&copy; '.date('Y').' <a style="text-decoration:underline;" target="_blank" title="↗ Gofas.net" href="https://gofas.net">Gofas.net</a> | <a style="text-decoration:underline;" target="_blank" title="↗ Gofas.net" href="https://gofas.net/?p='.$module_page.'#changelog">'.$module_version.'</a> | <a  style="text-decoration:underline;"target="_blank" title="↗ Documentação" href="https://gofas.net/?p='.$module_page.'">Documentação</a> | <a style="text-decoration:underline;" target="_blank" title="↗ Fórum de Suporte" href="https://gofas.net/foruns/">Suporte</a>.</p>
    				<p style="font-size: 12px;">
    				Ao utilizar esse módulo você concorda com nosso <a style="text-decoration:underline;" target="_blank" title="↗ Contrato de licença de uso de software" href="https://gofas.net/?p=9340">contrato de licença de uso de software</a>.
    				</p>
    				'.$check_updates['message'].'
    				</div>',
    			),
    		);
			if(file_exists(__DIR__.'/custom/config.php')){
				include __DIR__.'/custom/config.php';
				if(is_array($gefip_custom_config) and is_array($renderize) and is_array($footer)){
					$separator_custom = ['separator_custom' => [
						'Description' => '
							<div class="gefip_separator">
								<h4>Configurações personalizadas</h4>
							</div>',
						],
					];
					$gefip_config = array_merge($renderize,$separator_custom,$gefip_custom_config,$footer);
				}
			}
    		if(!file_exists(__DIR__.'/custom/config.php') || !$gefip_custom_config and (is_array($renderize) and is_array($footer))){
    			$gefip_config = array_merge($renderize,$footer);
    		}
		}
    	return $gefip_config;
    }
}
if(!function_exists('gofasefipix_link')){
    function gofasefipix_link($params){
		if(stripos($_SERVER['REQUEST_URI'], 'viewinvoice.php') !== false ){
			$enable_pix = gefip_enable_pix();
		}
    	//if(stripos($_SERVER['REQUEST_URI'], 'viewinvoice.php') !== false ){
    		$log['params'] = $params;
    		if($params['amount'] >= $params['minimunamount']){	
    			$result .= '<script>
    			function copy_tooltip() {
    				var copyText = document.getElementById("qrcodeforcopy");
    				copyText.select();
    				copyText.setSelectionRange(0, 99999);
    				navigator.clipboard.writeText(copyText.value);
    				var tooltip = document.getElementById("copy_tooltip");
    				tooltip.innerHTML = "Copiado!"; //"Copied: " + copyText.value;
    			  }
    			  function outFunc() {
    				var tooltip = document.getElementById("copy_tooltip");
    				//tooltip.innerHTML = "Pix Copia e Cola";
    				setTimeout(function(){ tooltip.innerHTML = "Pix Copia e Cola"; }, 1000);
    			  }
    			</script>';
    			$result .= '<input type="hidden" id="system_url" value="'.gefip_whmcs_url('whmcs_url').'">';
    			$result .= '<input type="hidden" id="invoice_id" value="'.$params['invoiceid'].'">';
				$result .= '<script type="text/javascript" src="'.gefip_whmcs_url('whmcs_url').'/modules/gateways/gofasefipix/scripts.js" charset="UTF-8"></script>';
			
    			$params_api = gefip_api_connect();
				if(file_exists(__DIR__.'/custom/params.php')){
					include __DIR__.'/custom/params.php';
				}
    			$customer = gefip_customer($params['clientdetails']['id']);
    			$log['customer'] = $customer;
    			$saved_qrcode = gefip_get_local_qrc($params['invoiceid']);
    			$saved_qrcode_amount = (int)$saved_qrcode['amount']; // 4898
    			$invoice_int_amount = (int)preg_replace("/[^0-9]/", "", $params['amount']); // 4898
    			$saved_qrcode_float_amount = (float)number_format(($saved_qrcode['amount']/100), 2,'.',''); // 48.98
    			$log['saved_qrcode_amount'] = $saved_qrcode_amount;
    			$log['invoice_int_amount'] = $invoice_int_amount;
    			$log['saved_qrcode_float_amount'] = $saved_qrcode_float_amount;
    			$log['saved_qrcode'] = $saved_qrcode;
    			$GetInvoiceResults			= localAPI('getinvoice',array('invoiceid'=>$params['invoiceid'] ), (int)gefip_setup_admin()['id'] );
    			$datediff = gefip_datediff($GetInvoiceResults['duedate'],$params['diasparavencimento']);
    			$log['datediff'] = $datediff;
    			$now_int = (int)date('Ymd');
    			$billet_duedate_int = (int)preg_replace("/[^0-9]/", "", $saved_qrcode['duedate']);
    			if($saved_qrcode['qrcode'] and $saved_qrcode_amount === $invoice_int_amount and $billet_duedate_int >= $now_int ){
    				$charge_verify = gefip_charge_verify($saved_qrcode['charge_id']);
    				$log['charge_verify'] = $charge_verify;
    				if((string)$charge_verify['result']['status'] === (string)'paid'){
    					$add_trans = gefip_add_trans($params['clientdetails']['id'], $params['invoiceid'], (float)number_format( $charge_verify['result']['total_paid_cents']/100,  2, '.', ''), (float)number_format( $charge_verify['result']['taxes_paid_cents']/100,  2, '.', ''), 'gefip-'.$saved_qrcode['charge_id'].'-'.$params_api['api_mode'], 'Pix pago - confirmação ao acessar a fatura');
    					header_remove();
    					header("Location: ".gefip_whmcs_url('whmcs_url').'/viewinvoice.php?id='.$params['invoiceid'],true,303);
    					exit;
    				}
    				$result .= $params['message'];
					$result .= '<img style="width: 200px;border: 1px solid #ccc;" src="'.$saved_qrcode['qrcode'].'">';
    				//$result .= '<a target="_blank" class="btn btn-default" style=" float: left;font-size: 14px;" href="'.$saved_qrcode['qrcode'].'">Visualizar o Pix</a>';
    				$result .= '<input value="'.$saved_qrcode['qrcode_text'].'" id="qrcodeforcopy" style="width: 0px;height: 0px;font-size: 0px;padding: 0px;display:none;">';
    				$result .= '<button style="position: relative;font-size: 14px; display: inline-block;width: 200px;"  id="copy_tooltip" class="btn btn-default" onclick="copy_tooltip()" onmouseout="outFunc()">Pix Copia e Cola</button>';
    				$log['saved_qrcode'] = $saved_qrcode;
    				if($error){
    					$result = '<b style="color:red;">Erro: '.$error.'</b>';
    				}
    				if($params['log']){
    					foreach( Capsule::table('tblconfiguration') -> where('setting','=','gefip_version') -> get(['value']) as $gefip_version_ ){
    						$gefip_version			= $gefip_version_->value;
    					}
    					logModuleCall('gofasefipix','gofasefipix_link',array('module_version'=>$gefip_version,'postfields'=>$postfields),'', $log );
    				}
    				if(!$error and $params['redirecttobillet'] and stripos($_SERVER['REQUEST_URI'], 'viewinvoice') ){
    					header_remove();
    					header("Location: ".$saved_qrcode['qrcode'],true,303);
    					exit;
    				}
    				else {
    					return $result;
    				}
    			}
    			if(!$saved_qrcode['qrcode'] || !$saved_qrcode['qrcode_text'] || $saved_qrcode_amount !== $invoice_int_amount || $billet_duedate_int < $now_int){
    				$line_items = array();
    				foreach( $GetInvoiceResults['items']['item'] as $Value){
    					if($Value['amount'] < (float)'0.00'){
							$line_items[]	= [
								'description'=>substr( $Value['description'],  0, 80),
								'quantity'=>1,
								'price_cents'=> '-'.(int)preg_replace("/[^0-9]/", "", $Value['amount']),
							];
						}
						else{
							$line_items[]	= [
    							'description'=>substr( $Value['description'],  0, 80),
    							'quantity'=>1,
    							'price_cents'=> (int)preg_replace("/[^0-9]/", "", $Value['amount']),
    						];
						}
    				}
					$postfields = [
    					'email'=>$customer['email'],
						'due_date'=> $datediff['duedate'],
						//'expires_in'=>$params['diasparavencimento'],
						'items'=>$line_items,
						'payable_with'=>['pix'],
						'notification_url'=>gefip_whmcs_url('whmcs_url').'/modules/gateways/gofasefipix.php',
						'payer' => [
							'name'=> $customer['name'],
							'cpf_cnpj'=> $customer['document'],
							'email'=>$customer['email'],
							'address'=> [
								'zip_code'=> $customer['postcode'],
								'street'=> $customer['address'],
								'number'=> $customer['number'],
								'complement'=> $customer['complement'],
								'district'=> $customer['neighborhood'],
								'city'=> $customer['city'],
								'state'=> $customer['state']
							],
						],
    				];
    				$qrcode_ = gefip_charge($postfields);
    				if((int)$qrcode_['result_code'] !== (int)200){
    					//$error .= $qrcode_['result_code'].': ';
    					if(is_array($qrcode_['result']['errors'])){
							foreach($qrcode_['result']['errors'] as $key=>$value){
    							$error .= $key.' '.implode(", ",$value);
    						}
						}
    				}
    				$log['postfields_json'] = json_encode($postfields['charge']);
    				$log['qrcode_'] = $qrcode_;
    				if($qrcode_['result']['id']){
                        if(!$saved_qrcode['qrcode'] || !$saved_qrcode['qrcode_text']){
    						$save_qrc = gefip_save_qrc(
    							[
    								'invoice_id'=>$params['invoiceid'],
    								'charge_id'=>$qrcode_['result']['id'],
    								'amount'=>$invoice_int_amount,
    								'duedate'=>(string)$qrcode_['result']['due_date'],
    								'qrcode'=>$qrcode_['result']['pix']['qrcode'],
    								'qrcode_text'=>$qrcode_['result']['pix']['qrcode_text'],
    								'api_mode'=>$params_api['api_mode'],
    							]
    						);
    						if($save_qrc !== 'success'){
    							$error .= $save_qrc;
    						}
    					}
    					if($saved_qrcode['qrcode']){
    						$update_qrc = gefip_update_qrc(
    							[
    								'invoice_id'=>$params['invoiceid'],
    								'charge_id'=>$qrcode_['result']['id'],
    								'amount'=>$invoice_int_amount,
    								'duedate'=>(string)$qrcode_['result']['due_date'],
    								'qrcode'=>$qrcode_['result']['pix']['qrcode'],
    								'qrcode_text'=>$qrcode_['result']['pix']['qrcode_text'],
    								'api_mode'=>$params_api['api_mode'],
    							]
    						);
    						//$update_qrc = gefip_update_qrc($update_qrc);
    						if($update_qrc !== 'success'){
    							$error .= $update_qrc;
    						}
    					}
    					$result .= $params['message'];
						$result .= '<img style="width: 200px;border: 1px solid #ccc;" src="'.$qrcode_['result']['pix']['qrcode'].'">';
    					//$result .= '<a target="_blank" class="btn btn-default" style=" float: left;font-size: 14px;" href="'.$qrcode_['result']['pix']['qrcode'].'">Visualizar o Pix</a>';
    					$result .= '<input value="'.$qrcode_['result']['pix']['qrcode_text'].'" id="qrcodeforcopy" style="width: 0px;height: 0px;font-size: 0px;padding: 0px;display:none;">';
    					$result .= '<button style="position: relative;font-size: 14px; display: inline-block;width: 200px;"  id="copy_tooltip" class="btn btn-default" onclick="copy_tooltip()" onmouseout="outFunc()">Pix Copia e Cola</button>';
    				}
    			}
    			if($error){
    		    	$result = '<b style="color:red;">Erro: '.$error.'</b>';
    			}
    			if($params['log']){
    				foreach( Capsule::table('tblconfiguration') -> where('setting','=','gefip_version') -> get(['value']) as $gefip_version_ ){
    					$gefip_version			= $gefip_version_->value;
    				}
    				logModuleCall('gofasefipix','gofasefipix_link',array('module_version'=>$gefip_version,'postfields'=>$postfields),'', $log );
    				//echo '<pre style="height:250px;">',$url,'<br>',print_r($log),'</pre>';
    			}
    			if(!$error and $params['redirecttobillet'] and stripos($_SERVER['REQUEST_URI'], 'viewinvoice') ){
    				header_remove();
    				header("Location: ".$qrcode_['result']['pix']['qrcode'],true,303);
    				exit;
    			}
    			else {
    				return $result;
    			}
    		}
    		elseif( $params['amount'] < $params['minimunamount']){
    			$error .= 'O valor mínimo para utilizar esse método de pagamento é '.number_format( $params['minimunamount'] ,  2, ',', '.').'.';
    			return $error;
    		}
    	//}
    }
}
if(!function_exists('gefip_charge')){
	function gefip_charge($postfields){
		$params_api = gefip_api_connect();
    	$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $params_api['charge_url'].'/invoices',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPHEADER => array(
		    	'Authorization: Basic '.base64_encode((string)$params_api['api_token'].':'),
				'Content-Type: application/json',
				'Accept: application/json',
		  	),
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => json_encode($postfields),
		));
		$result = json_decode(curl_exec($curl),true);
		$result_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return ['result_code'=>$result_code,'result'=>$result];
	}
}
if(!function_exists('gefip_charge_verify')){
	function gefip_charge_verify($charge_id){
		$params_api = gefip_api_connect();
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $params_api['charge_url'].'/invoices/'.$charge_id,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => array(
		    	'Authorization: Basic '.base64_encode((string)$params_api['api_token'].':'),
				'Content-Type: application/json',
				'Accept: application/json',
		  	),
		));
		$result = json_decode(curl_exec($curl),true);
		$result_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return ['result_code'=>$result_code,'result'=>$result];
	}
}
if(!function_exists('gefip_get_string_between')){
	function gefip_get_string_between($string, $start, $end){
		$string = " ".$string;
		$ini = strpos($string,$start);
		if ($ini == 0) return "";
		$ini += strlen($start);   
		$len = strpos($string,$end,$ini) - $ini;
		return substr($string,$ini,$len);
	}
}
if(!function_exists('gefip_file_exists_check')){ #10
    function gefip_file_exists_check($file,$sucess_msg=NULL,$error_msg=NULL){
		$file = gefip_whmcs_url('root_dir').$file;
    	if(!file_exists($file)){
			if(!$error_msg){
				$error_msg .= '<p style="color: red;padding: 10px;border-left: 2px solid red;padding: 5px 10px 12px 12px;">';
				$error_msg .= '<span style="font-size: 24px;">Atenção!</span><br>';
	    	    $error_msg .= 'Arquivo <b>'.$file.'</b> não encontrado.';
				$error_msg .= '<br>É necessário instalar o <i>hook</i> que acompanha o módulo para todos os recursos funcionarem. <a style="text-decoration:underline;color:red" target="_blank" href="https://gofas.net/gefip/#instalation">Saiba mais </a>&#10138;';
				$error_msg .= '</p>';
				return $error_msg;
			}
			if($error_msg){
				return $error_msg;
			}
		}
		else{
			if($sucess_msg){
				return $sucess_msg;
			}
    	    return;
    	}
    }
}
if(!function_exists('gefip_add_trans')){
	function gefip_add_trans( $user_id, $invoice_id, $amount, $fee, $charge_id, $description ){
		$params = getGatewayVariables('gofasefipix');
 		$addtransvalues['userid'] = $user_id;
 		$addtransvalues['invoiceid'] = $invoice_id;
 		$addtransvalues['description'] = $description;
 		$addtransvalues['amountin'] = $amount;
 		$addtransvalues['fees'] = $fee;
 		$addtransvalues['paymentmethod'] = 'gofasefipix';
 		$addtransvalues['transid'] = $charge_id;
 		$addtransvalues['date'] = date('d/m/Y');
		$addtransresults = localAPI( "addtransaction", $addtransvalues, (int)gefip_setup_admin()['id']);
		$delete_qrc = Capsule::table('gofasefipix')->where('invoice_id', '=',$invoice_id)->delete();
		$gefip_update_stats = gefip_update_stats();
		
		if( $addtransresults['result'] === 'success'){
			return array('values'=>$addtransvalues, 'result'=>$addtransresults);
		}
		elseif($addtransresults['result'] !== 'success'){
			$error = '<b>Não foi possível gravar a transação.</b>';
			return array('error'=>$error, 'values'=>$addtransvalues, 'result'=>$addtransresults,'update_stats'=>$gefip_update_stats);
		}
	}
}
if(!function_exists('gefip_customer')){
	function gefip_customer($client_id){
		//Determine custom fields id
		$params = getGatewayVariables('gofasefipix');
		$client = localAPI('GetClientsDetails',array( 'clientid' => $client_id, 'stats' => false, ), (int)gefip_setup_admin()['id']);
		foreach( Capsule::table('tblcustomfields')->where('type','=','client')->get() as $customfield ){
			$customfield_id = $customfield->id;
			$customfield_name = strtolower($customfield->fieldname);
			// cpf
			if(strpos($customfield_name, 'cpf') !== false and strpos($customfield_name,'cnpj') === false){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$cpf_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
				}
			}	
			// cnpj
			if(strpos($customfield_name, 'cnpj') !== false and strpos($customfield_name,'cpf') === false){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$cnpj_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
				}
			}
			// cpf + cnpj
			if( strpos( $customfield_name, 'cpf') !== false and strpos( $customfield_name, 'cnpj') !== false ){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$cpf_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
					$cnpj_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
				}
			}
			// Inscrição Estadual
			if( strpos( $customfield_name, 'inscrição estadual') !== false){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$ie = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
				}
			}
			// Complemento Custom Field
			if( strpos( $customfield_name, 'complemento') !== false){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$complement = $customfieldvalue->value;
				}
			}
			// Número Custom Field
			if( strpos( $customfield_name, 'numero')!== false ||  strpos( $customfield_name, 'número')!== false ){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$number = $customfieldvalue->value;
				}
				if(!$number){
					$number = preg_replace('/[^0-9]/', '', $client['address1']);
				}
			}
			else {
				$number = preg_replace('/[^0-9]/', '', $client['address1']);
			}
			// Emitir Custom Field
			if( strpos( $customfield_name, 'emitir nfe')!== false || strpos( $customfield_name, 'emitir nfse')!== false || strpos( $customfield_name, 'emitir nfs-e')!== false || strpos( $customfield_name, 'emitir nf-e')!== false){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$issue_nfe = $customfieldvalue->value;
				}
				if(!$issue_nfe){
					$issue_nfe = false;
				}
			}
			// nascimento
			if( strpos( $customfield_name, 'nascimento') ){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$birt_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
					$birthday_pre			= preg_replace('/[^\da-z]/i', '', $birt_customfield_value);
					if(strlen($birthday_pre) === 8){
						$birth_ = $birthday_pre;
					}
					elseif( strlen($birthday_pre) === 7 ){
						$birth_ = '0'.$birthday_pre;
					}
					$birth_Y					= substr($birth_, -4);
					$birth_m					= substr($birth_, 2, -4);
					$birth_d					= substr($birth_, 0, -6);
					$birthday_us = $birth_Y.'-'.$birth_m.'-'.$birth_d; // 2021-02-20
					$birthday_br = $birth_d.'/'.$birth_m.'/'.$birth_Y; // 20/02/2021
					$birthday_raw = $customfieldvalue->value;
				}
			}
			foreach(Capsule::table('tblcustomfieldsvalues')->where('fieldid','=',$customfield_id)->where('relid','=',$client_id)->get(array('value')) as $customfieldvalue ){
				$custom_fields[$customfield_name] = $customfieldvalue->value;
			}
		}
		//
		// Cliente possui CPF e CNPJ
		// CPF com 1 nº a menos, adiciona 0 antes do documento
		if( strlen( $cpf_customfield_value ) === 10 ){
			$cpf = '0'.$cpf_customfield_value;
		}
		// CPF com 11 dígitos
		elseif( strlen( $cpf_customfield_value ) === 11){
			$cpf = $cpf_customfield_value;
		}
		// CNPJ no campo de CPF com um dígito a menos
		elseif( strlen( $cpf_customfield_value ) === 13 ){
			$cpf = false; 
			$cnpj = '0'.$cpf_customfield_value;
		}
		// CNPJ no campo de CPF
		elseif( strlen( $cpf_customfield_value ) === 14 ){
			$cpf 				= false;
			$cnpj				= $cpf_customfield_value;
		}
		// cadastro não possui CPF
		elseif(!$cpf_customfield_value || strlen( $cpf_customfield_value ) !== 10 || strlen($cpf_customfield_value) !== 11 || strlen( $cpf_customfield_value ) !== 13 || strlen($cpf_customfield_value) !== 14 ){	
			$cpf = false;
		}
		// CNPJ com 1 nº a menos, adiciona 0 antes do documento
		if( strlen($cnpj_customfield_value) === 13 ){
			$cnpj = '0'.$cnpj_customfield_value;
		}
		// CNPJ com nº de dígitos correto
		elseif( strlen($cnpj_customfield_value) === 14 ){
			$cnpj = $cnpj_customfield_value;
		}
		// Cliente não possui CNPJ
		elseif(!$cnpj_customfield_value and strlen( $cnpj_customfield_value ) !== 14 and strlen($cnpj_customfield_value) !== 13 and strlen( $cpf_customfield_value ) !== 13 and strlen( $cpf_customfield_value ) !== 14  ){
			$cnpj = false;
		}

		if( ( $cpf and $cnpj ) or ( !$cpf and $cnpj ) ){
			if( $client['companyname'] ){
				$name	= $client['companyname'];
			}
			elseif(!$client['companyname'] ){
				$name	= $client['firstname'].' '.$client['lastname'];
			}
			$doc_type	= 'J';
			$document	= $cnpj;
		}
		elseif( $cpf and !$cnpj ){
			$name	= $client['firstname'].' '.$client['lastname'];
			$doc_type	= 'F';
			$document	= $cpf;
		}
		/// Formated Array
		$customer=[
			'id'=>$client_id,
			'email'=>$client['email'],
			'name'=>$name,
			'names'=>['firstname'=>$client['firstname'],'lastname'=>$client['lastname'],'companyname'=>$client['companyname']],
			'address'=>str_replace(',','',preg_replace('/[0-9]+/i','',$client['address1'],1)),
			'number'=>$number,
			'neighborhood'=>$client['address2'],
			'complement'=>$complement,
			'city'=>$client['city'],
			'state'=>$client['state'],
			'postcode'=>preg_replace("/[^\da-z]/i", "",$client['postcode']),
			'phone'=>preg_replace('/[^\da-z]/i', '', $client['phonenumber']),
			'doc_type'=>$doc_type,
			'document'=>$document,
			'ie'=>$ie,
			'issue_nfe'=>$issue_nfe,
			'birthday'=>['raw'=>$birthday_raw,'br'=>$birthday_br,'us'=>$birthday_us],
			'custom_fields'=>$custom_fields,
		];
		return $customer;
	}
}
if(!function_exists('gefip_save_qrc')){
	function gefip_save_qrc($qr_code){
		$data = array(
			'invoice_id'=>$qr_code['invoice_id'],
			'charge_id'=>$qr_code['charge_id'],
			'amount'=>$qr_code['amount'],
			'duedate'=>$qr_code['duedate'],
			'qrcode'=>$qr_code['qrcode'],
			'qrcode_text'=>$qr_code['qrcode_text'],
			'api_mode'=>$qr_code['api_mode'],
			'created_at'=>date("Y-m-d H:i:s"),
			'updated_at'=>date("Y-m-d H:i:s"),
		);
	try {
		$save_qrc = Capsule::table('gofasefipix')->insert($data);
		return 'success';
	}
	catch (\Exception $e){
		return $e->getMessage();
	}
}}
if(!function_exists('gefip_update_qrc')){
	function gefip_update_qrc($data){
		$params = getGatewayVariables('gofasefipix');
		$local_qrc = gefip_get_local_qrc($data['invoice_id']);
		$data['created_at'] = $local_qrc['created_at'];
		$data['updated_at']= date("Y-m-d H:i:s");
		
	try {
		$update_qrc = Capsule::table('gofasefipix')->where('invoice_id', '=',$data['invoice_id'])->update($data);
		if($params['log']){
			logModuleCall('gofasefipix','gefip_update_qrc',array('data'=>$data),'post',array('update_qrc' => $update_qrc),'replaceVars');
		}
		return 'success';
	}
	catch (\Exception $e){
		if($params['log']){
			logModuleCall('gofasefipix','gefip_update_qrc',array('data'=>$data),'post',array('update_qrc' => $update_qrc),'replaceVars');
		}
		return $e->getMessage();
	}
}}
if(!function_exists('gefip_get_local_qrc')){
	function gefip_get_local_qrc($invoice_id){
		$params_api = gefip_api_connect();
		foreach( Capsule::table('gofasefipix')->where('invoice_id','=', $invoice_id)->where('api_mode','=',$params_api['api_mode'])->get() as $key => $value ){
			$qrc_for_invoice[$key] = json_decode(json_encode($value), true);
		}
		return $qrc_for_invoice['0'];
	}
}
if(!function_exists('gefip_update_stats')){
	function gefip_update_stats(){
		$params = getGatewayVariables('gofasefipix');
		if($params['sandbox']){
			return;
		}
		$whmcs_url = gefip_whmcs_url();
		$setup_admin = gefip_setup_admin();
		$query = '?software_id=15590&install_url='.$whmcs_url['admin_url'].'&current_version='.gefip_get_local_version().'&installer_email='.$setup_admin['email'].'&installer_firstname='.$setup_admin['firstname'].'&installer_lastname='.$setup_admin['lastname'].'&action=charge'.gefip_sysinfo();
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl, CURLOPT_URL, 'https://gofas.net/br/updates/stats.php'.$query);
		$response = curl_exec($curl);
		$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		$return = ['query'=>$query,'response'=>$response,'http_code'=>$http_status];
		return $return;
	}
}
if(!function_exists('gefip_get_local_version')){
	function gefip_get_local_version(){
	foreach( Capsule::table('tblconfiguration')->where('setting','=','gefip_version')->get(['value']) as $version_ ){
		$version		= json_decode($version_->value, true);
		$local_version			= $version['local_version'];
	}
	return $local_version;
}}
if(!function_exists('gefip_reset_local_version')){
	function gefip_reset_local_version(){
        try{
	        Capsule::table('tblconfiguration')->where('setting','=','gefip_version')->delete();
	        return 'sucess';
        }
        catch (\Exception $e){
            return $e->getMessage();
        }
}}
if(!function_exists('gefip_sysinfo')){
	function gefip_sysinfo(){
		foreach( Capsule::table('tblconfiguration')
		->where('setting','=','Version')
		->get(['value']) as $data1 ){
			$Version = $data1->value;
		}
		foreach( Capsule::table('tblconfiguration')
		->where('setting','=','CronPHPVersion')
		->get(['value']) as $data1 ){
			$PHPVersion = $data1->value;
		}
		return '&whmcs_version='.$Version.'&php_version='.$PHPVersion;
	}
}
if(!function_exists('gefip_verify_module_updates')){
	function gefip_verify_module_updates($page_id,$referer,$module_version){
		foreach( Capsule::table('tblconfiguration')->where('setting','=','gefip_version')->get(['value','created_at','updated_at']) as $version_ ){
			$version		= json_decode($version_->value, true);
			$local_version	= $version['local_version'];
			$last_version	= $version['last_version'];
			$embed			= $version['check'];
			$created_at		= $version_->created_at;
			$updated_at		= $version_->updated_at;
		}
		if(!$version){
			$get_version = gefip_get_version($page_id,$referer,$module_version);
			$get_embed	 = gefip_get_embed($page_id,$referer,$module_version);
			
			if((int)$get_version['http_code'] !== 200){
				$error .= $get_version['http_code'].' '.$get_version['version'];
			}
			else{
				$available_version = $get_version['version'];
			}
		}
		if($version and strtotime($updated_at) < strtotime("-1 day")){
			$get_version = gefip_get_version($page_id,$referer,$module_version);
			$get_embed	 = gefip_get_embed($page_id,$referer,$module_version);
			if((int)$get_version['http_code'] !== 200){
				$error .= $get_version['http_code'].' '.$get_version['version'];
			}
			else{
				$available_version = $get_version['version'];
			}
		}
		if($version and (string)$module_version !== (string)$local_version){
			$get_version = gefip_get_version($page_id,$referer,$module_version);
			$get_embed	 = gefip_get_embed($page_id,$referer,$module_version);
			if((int)$get_version['http_code'] !== 200){
				$error .= $get_version['http_code'].' '.$get_version['version'];
			}
			else{
				$available_version = $get_version['version'];
			}
		}
		if($version and strtotime($updated_at) > strtotime("-1 day")){
			$available_version = $last_version;
		}
		if(!$version and $get_version['version'] and $get_embed['embed']){
			$local_version = $module_version;
			$last_version = $get_version['version'];
			$embed		  = gefip_encrypt($get_embed['embed']);
			$created_at		= date("Y-m-d H:i:s");
			$updated_at		= date("Y-m-d H:i:s");

			try { Capsule::table('tblconfiguration')->insert(array(
				'setting' => 'gefip_version',
				'value' => json_encode([
					'local_version'=>$module_version,
					'last_version'=>$get_version['version'],
					'check'=>gefip_encrypt($get_embed['embed']),
					'admin'=>gefip_current_admin(),
				]),
				'created_at' => $created_at,
				'updated_at' => $updated_at
			));
			}
			catch (\Exception $e){
				$error .= $e->getMessage();
			}
		}
		if($version and $get_version['version'] and $get_embed['embed'] and strtotime($updated_at) < strtotime("-1 day") and (
			$available_version !== $module_version ||
			$local_version !== $module_version ||
			$last_version !== $available_version
		)){
			try {
				Capsule::table('tblconfiguration')->where('setting','gefip_version')->update([
					'value' => json_encode([
						'local_version'=>$module_version,
						'last_version'=>$available_version,
						'check'=>gefip_encrypt($get_embed['embed']),
						'admin'=>gefip_current_admin(),
					]),
					'created_at' =>  $created_at,
					'updated_at' => date("Y-m-d H:i:s")]
				);
			}
			catch (\Exception $e){
				$error .= $e->getMessage();
			}
		}
		// update
		if($version and $get_version['version'] and $get_embed['embed'] and (string)$local_version !== (string)$module_version){
			try {
				Capsule::table('tblconfiguration')->where('setting','gefip_version')->update([
					'value' => json_encode([
						'local_version'=>$module_version,
						'last_version'=>$available_version,
						'check'=>gefip_encrypt($get_embed['embed']),
						'admin'=>gefip_current_admin(),
					]),
					'created_at' =>  $created_at,
					'updated_at' => date("Y-m-d H:i:s")]
				);
			}
			catch (\Exception $e){
				$error .= $e->getMessage();
			}
		}
		$module_version_int = (int)preg_replace("/[^0-9]/", "", $module_version);
		$available_version_int = (int)preg_replace("/[^0-9]/", "", $available_version);
		if( $available_version_int === $module_version_int ){
			$message = '<p style="color: green"><i class="fas fa-check-square"></i> Você está executando a versão mais recente do módulo.</p>';
            $message .= '<p>Última verificação '.date('d/m/Y à\s H:i', strtotime($updated_at)).' - <a style="text-decoration:underline;" href="'.gefip_whmcs_url('admin_url').'/configgateways.php?manage=gofasefipix&resetversion=gofasefipix#m_gofasefipix">verificar agora</a>.</p>';
		}
		if( $available_version_int > $module_version_int ){
			$message = '<p style="font-size: 14px; color: red;"><i class="fas fa-exclamation-triangle"></i> Atualização disponível, verifique a <a style="color:#CC0000;text-decoration:underline;" href="https://gofas.net/?p='.$page_id.'" target="_blank">versão '.$available_version.'</a>. Última verificação '.date('d/m/Y H:i', strtotime($updated_at)).'.';
            $message .= '<p>Última verificação '.date('d/m/Y à\s H:i', strtotime($updated_at)).' - <a style="text-decoration:underline;" href="'.gefip_whmcs_url('admin_url').'/configgateways.php?manage=gofasefipix&resetversion=gofasefipix#m_gofasefipix">verificar agora</a>.</p>'; #9
		}
		if( $available_version_int < $module_version_int ){
			$message = '<p style="font-size: 14px; color: orange;"><i class="fas fa-exclamation-triangle"></i> Você está executando uma versão Beta desse módulo.<br>Baixar versão estável: <a style="color:#CC0000;text-decoration:underline;" href="https://gofas.net/?p='.$page_id.'" target="_blank">v'.$available_version.'</a>. Última verificação '.date('d/m/Y H:i', strtotime($updated_at)).'.';
            $message .= '<p>Última verificação '.date('d/m/Y à\s H:i', strtotime($updated_at)).' - <a style="text-decoration:underline;" href="'.gefip_whmcs_url('admin_url').'/configgateways.php?manage=gofasefipix&resetversion=gofasefipix#m_gofasefipix">verificar agora</a>.</p>'; #9
        }
		return [
			'version'=>$version,
			'get_version'=>$get_version,
			'message' => $message,
			'check'=> $embed,
			'error' => $error,
		];
	}
}
if(!function_exists('gefip_version')){
	function gefip_version($opt=1){
		foreach( Capsule::table('tblconfiguration') -> where('setting', '=', 'gefip_version') -> get( array( 'value','created_at') ) as $gefip_version_ ){
			$gefip_version				= $gefip_version_->value;
			$gefip_version_created_at	= $gefip_version_->created_at;
		}
		if($opt=1){ // local_version string
			$version = json_decode($gefip_version, true);
			return $version['local_version'];
		}
		if($opt=2){ // local_version integer
			$version = json_decode($gefip_version, true);
			return (int)preg_replace("/[^0-9]/", "", $version['local_version']);
		}
		if($opt=3){ // full
			return$gefip_version;
		}
	}
}
if(!function_exists('gefip_current_admin')){
	function gefip_current_admin(){
		$currentUser = new \WHMCS\Authentication\CurrentUser;
		$admin = json_decode(json_encode($currentUser->admin()),true);
		return $admin;
	}
}
if(!function_exists('gefip_setup_admin')){
	function gefip_setup_admin(){
	foreach( Capsule::table('tblconfiguration')->where('setting','=','gefip_version')->get(['value']) as $version_ ){
		$version		= json_decode($version_->value, true);
		$admin			= $version['admin'];
	}
	return $admin;
}}
if(!function_exists('gefip_datediff')){
	function gefip_datediff($invoice_duedate,$diasparavencimento=1){
		if( $diasparavencimento and $diasparavencimento > 0 and $diasparavencimento > 1){
			$diasParaVencimento = '+'.$diasparavencimento.' days';
		}
		if( $diasparavencimento == '0'){
			$diasParaVencimento = '+1 day';
		}
		if( $diasparavencimento == '1'){
			$diasParaVencimento = '+1 day';
		}
		if( $diasparavencimento > '30'){
			$diasParaVencimento = '+30 days';
		}
		if(!$diasparavencimento ){
			$diasParaVencimento = '+1 day';
		}
		if( $invoice_duedate > date('Y-m-d') ){
			$billet_duedate = $invoice_duedate;
		}
		if( $invoice_duedate === date('Y-m-d') ){
			$billet_duedate = date('Y-m-d', strtotime($diasParaVencimento));
		}
		// Se fatura já venceu, data de vencimento do qrcode = Hoje + X dia(s)
		if( $invoice_duedate < date('Y-m-d') ){
			$billet_duedate = date('Y-m-d', strtotime( $diasParaVencimento ));
		}
		$now = (int)date('Ymd');
		$due_date = (int)preg_replace("/[^0-9]/", "", $billet_duedate);
		$datediff = $due_date-$now;
		return ['datediff'=>$datediff,'duedate'=>$billet_duedate];
	}
}
if(!function_exists('gefip_get_protected_property')){
	function gefip_get_protected_property($object, $property){
	    $reflectedClass = new \ReflectionClass($object);
	    $reflection = $reflectedClass->getProperty($property);
	    $reflection->setAccessible(true);
	    return $reflection->getValue($object);
	}
}
if(!function_exists('gefip_qrcode_mergetags_fields')){
    function gefip_qrcode_mergetags_fields($vars){
        $gefip_merge_fields = array();
	    $gefip_merge_fields['gefip_qrcode']		= 'Efi Pix: URL a imagem do QR code';
		$gefip_merge_fields['gefip_qrcode_text']	= 'Efi Pix: Pix Copia e Cola';
        return $gefip_merge_fields;
    }
}
if(!function_exists('gefip_qrcode_mergetags')){
    function gefip_qrcode_mergetags($vars){
		if(
			$vars['messagename'] === 'Invoice Created' ||
			$vars['messagename'] === 'Invoice Payment Reminder' ||
			$vars['messagename'] === 'First Invoice Overdue Notice' ||
			$vars['messagename'] === 'Second Invoice Overdue Notice' ||
			$vars['messagename'] === 'Third Invoice Overdue Notice'
		){
			$params = getGatewayVariables('gofasefipix');
			$gefip_merge_fields	= array();
			$invoice			= localAPI( 'GetInvoice', array('invoiceid' => $vars['relid']), (int)gefip_setup_admin()['id']);
			if( $invoice['total'] > '0.00' and $invoice['paymentmethod'] === 'gofasefipix'){
				// Saved Billets
				$qrcode_saved = array();
				foreach( Capsule::table('gofasefipix')->where('invoice_id','=',$vars['relid'])->get(['qrcode','qrcode_text']) as $key => $value ){
					$qrcodes_for_invoice[$key]=json_decode(json_encode($value), true);
				}
				$qrcode_saved = $qrcodes_for_invoice['0']; // Array
				// Merge Fields
				if (!array_key_exists('gefip_qrcode', $vars['mergefields'])) {
					$gefip_merge_fields['gefip_qrcode'] = $qrcode_saved['qrcode'];
				}
				if (!array_key_exists('gefip_qrcode_text', $vars['mergefields'])) {
					$gefip_merge_fields['gefip_qrcode_text'] = $qrcode_saved['qrcode_text'];
				}
			}
    	}
		if($params['log']){
			logModuleCall('gofasefipix','email_qrcode',$vars,'',$invoice);
		}
		return $gefip_merge_fields;
    }
}
if(!function_exists('gefip_check_schedule')){
    function gefip_check_schedule(){
        $params = getGatewayVariables('gofasefipix');
        $start_at = substr(preg_replace('/[^\da-z]/i','',$params['verifypaymentsat']),0,4) ?: '0500';
        $max_invoices = $params['maxinvoicespercheck'] ?: '100';
        $total_queue_invoices = Capsule::table('tblinvoices')->where('status','=','Unpaid')->where('paymentmethod','=','gofasefipix')->count();
		foreach( Capsule::table('tbltransientdata')
            ->where('name','=','efi.Pix.Charge.Verification')
            ->orderBy('id','desc')
            ->take('1')
            ->get() as $value){
                $tbltransientdata=json_decode(json_encode($value),true);
                $tbltransientdata=json_decode($tbltransientdata['data'],true);
        }
		if((int)$start_at === 0){
			$start_at_ = '24';
		}
		else{
			$start_at_ = $start_at;
		}
		if((int)date('H') >= (int)$start_at_){
			$next_check_schedule = date('Ymd',strtotime('+1 day')).$start_at;
		}
		if((int)date('H') < (int)$start_at_){
			$next_check_schedule = date('Ymd').$start_at;
		}
        if((int)$total_queue_invoices >= 1){
            if(is_array($tbltransientdata) and (int)date('YmdHi') >= (int)$tbltransientdata['next']){
                foreach( Capsule::table('tblinvoices')
                    ->where('status','=','Unpaid')
                    ->where('paymentmethod','=','gofasefipix')
                    ->orderBy('id','asc')
                    ->take($max_invoices)
                    ->whereNotIn('id', $tbltransientdata['skip_invoices'] ?: ['0'])
                    ->get(['id']) as $queue_invoices_){
                        if($queue_invoices_->id){
                            $queue_invoices[]=$queue_invoices_->id;
                        }
                        else{
                            $queue_invoices=false;
                        }
                }
                if($queue_invoices){ // <----
                    if($tbltransientdata['skip_invoices']){
                        $skip_invoices = $tbltransientdata['skip_invoices'];
                    }
                    else{
                        $skip_invoices = [];
                    }
                    $data = [
                        'name'=>'efi.Pix.Charge.Verification',
                        'data'=>json_encode([
                            'next'=>date('YmdHi',strtotime('+300 seconds')),
                            'skip_invoices'=> array_merge($skip_invoices,$queue_invoices),
                        ]),
                        'expires'=>strtotime('+2 days'),
                    ];
                    $transientdata = Capsule::table('tbltransientdata')->where('name','=','efi.Pix.Charge.Verification')->update($data);
					unset($transientdata);
                    return $queue_invoices;
                }
                if(!$queue_invoices){ // <----
                    $data = [
                        'name'=>'efi.Pix.Charge.Verification',
                        'data'=>json_encode([
                            'next'=>$next_check_schedule,
                            'skip_invoices'=> '',
                        ]),
                        'expires'=>strtotime('+2 days'),
                    ];
                    $transientdata = Capsule::table('tbltransientdata')->where('name','=','efi.Pix.Charge.Verification')->update($data);
					unset($transientdata);
                    return false;
                }
            }
            if(!is_array($tbltransientdata)){
                foreach( Capsule::table('tblinvoices')
                    ->where('status','=','Unpaid')
                    ->where('paymentmethod','=','gofasefipix')
                    ->orderBy('id','asc')
                    ->take($max_invoices)
                    //->whereNotIn('id', $tbltransientdata['skip_invoices'])
                    ->get(['id']) as $queue_invoices_){
                        $queue_invoices[]=$queue_invoices_->id;
                }
                if($queue_invoices){ // <----
                    $data = [
                        'name'=>'efi.Pix.Charge.Verification',
                        'data'=>json_encode([
                            'next'=>date('YmdHi',strtotime('+300 seconds')),
                            'skip_invoices'=> $queue_invoices,
                        ]),
                        'expires'=>strtotime('+2 days'),
                    ];
                    $transientdata = Capsule::table('tbltransientdata')->insert($data);
					unset($transientdata);
                    return $queue_invoices;
                }
                if(!$queue_invoices){ // <----
                    $data = [
                        'name'=>'efi.Pix.Charge.Verification',
                        'data'=>json_encode([
                            'next'=>$next_check_schedule,
                            'skip_invoices'=> '',
                        ]),
                        'expires'=>strtotime('+2 days'),
                    ];
                    $transientdata = Capsule::table('tbltransientdata')->insert($data);
					unset($transientdata);
                    return false;
                }
            }
        }
		if((int)$total_queue_invoices <1 and !empty($tbltransientdata['skip_invoices'])){
			$data = [
				'name'=>'efi.Pix.Charge.Verification',
				'data'=>json_encode([
					'next'=>$next_check_schedule,
					'skip_invoices'=> '',
				]),
				'expires'=>strtotime('+2 days'),
			];
			$transientdata = Capsule::table('tbltransientdata')->update($data);
			unset($transientdata);
			return false;
		}
		if((int)$total_queue_invoices <1 and !is_array($tbltransientdata)){
			$data = [
				'name'=>'efi.Pix.Charge.Verification',
				'data'=>json_encode([
					'next'=>$next_check_schedule,
					'skip_invoices'=> '',
				]),
				'expires'=>strtotime('+2 days'),
			];
			$transientdata = Capsule::table('tbltransientdata')->insert($data);
			unset($transientdata);
			return false;
        }
		return;
    }
}
if(!function_exists('gefip_check_status_updates')){
	function gefip_check_status_updates($vars){
		$params = getGatewayVariables('gofasefipix');
		$params_api = gefip_api_connect();
	    //<
	    $check_schedule = gefip_check_schedule();
	    if(!is_array($check_schedule)){
	        return;
	    }
	    //>
	    if(is_array($check_schedule)){
		    try {
		    	$log = array();
		    	$qrcode = array();
		    	$invoices = array();
		    	// Unpaid invoices IDs
		    	foreach( Capsule::table('tblinvoices')
	                ->where('status','=','Unpaid')
	                ->where('paymentmethod','=','gofasefipix')
	                ->orderBy('id','asc')
	                ->whereIn('id',$check_schedule)
	                ->get(['id','total','userid']) as $tblinvoices){
		    		foreach( Capsule::table('gofasefipix')->where('invoice_id','=',$tblinvoices->id )->get(['charge_id']) as $local_qrcode){
		    			$qrcode = gefip_charge_verify($local_qrcode->charge_id);
		    			$qrcodes[$local_qrcode->charge_id] = $qrcode;
		    			if((int)$qrcode['result_code'] !== 200){
		    				$error	.= 'Erro ao verificar Pix: ' . json_encode($qrcode);
		    			}
		    			if($qrcode['result']['status'] === 'paid') {
		    				$invoices[$tblinvoices->id] = [
		    					'invoice_id'=>$tblinvoices->id,
		    					'trans_id'=>$local_qrcode->charge_id,
		    					'transaction_id'=>$local_qrcode->charge_id,
		    					'total'=>$tblinvoices->total,
		    					'user_id'=>$tblinvoices->userid,
		    					'paid_amount'=>(float)number_format(($qrcode['result']['total_paid_cents']/100), 2,'.',''),
		    					'fee'=>(float)number_format(($qrcode['result']['taxes_paid_cents']/100), 2,'.','')
		    				];
		    			}
		    		} // End Foreach
		    	} // End Foreach
		    	// Add Payments
		    	if (!empty($invoices)) {
		    		foreach ($invoices as $key => $value) {
		    			$log['invoice_value'][$value['invoice_id']] = $value;
		    			$log['invoice_id'][$value['invoice_id']] = $value['invoice_id'];
		    			if ( (float)$value['paid_amount'] > (float)$value['total'] ) {
		    				$update_invoice = localAPI('updateinvoice', array( 'invoiceid' => $value['invoice_id'], 'newitemdescription' => array('Acréscimos calculados na emissão do Pix'),'newitemamount' => array((float)($value['paid_amount'] - $value['total']))), (int)gefip_setup_admin()['id'] );
		    			}
		    			// - Billet amount is less than the invoice amount
		    			if ( (float)$value['paid_amount'] < (float)$value['total'] ) {
		    				$update_invoice = localAPI('updateinvoice', array( 'invoiceid' => $value['invoice_id'], 'newitemdescription' => array('Descontos calculados na emissão do Pix'),'newitemamount' => array((float)($value['paid_amount'] - $value['total']))), (int)gefip_setup_admin()['id'] );
		    			}
					
	                    $add_trans = gefip_add_trans($value['user_id'],$value['invoice_id'],$value['paid_amount'],$value['fee'],'gefip-'.$params_api['api_mode'].'-'.$value['trans_id'], 'Pix pago - confirmação via cron job');
		    			$update_invoice_log[$value['invoice_id']]=$update_invoice;
		    			$add_trans_log[$value['invoice_id']]=$add_trans;
		    		}
		    	}
		    }
		    catch (Exception $e) {
		    	$error	.= 'Erro ao listar qrcodes pagos: ' . $e->getMessage();
		    	$log['error'] = $error;
		    }
	    }
		$log['qrcodes'] = $qrcodes;
		$log['invoices'] = $invoices;
		$log['update_invoice'] = $update_invoice;
		$log['add_trans'] = $add_trans;
		if($params['log']){
			logModuleCall('gofasefipix','AfterCronJob',array('module_version'=>gefip_version(),'params'=>$params),'', array($log) );
		}
		return;
	}
}
if($_REQUEST['invoice_id']){
	require_once __DIR__.'./../../../init.php';
	require_once  __DIR__.'./../../../includes/gatewayfunctions.php';
	require_once  __DIR__.'./../../../includes/invoicefunctions.php';
	$params = getGatewayVariables('gofasefipix');
	$params_api = gefip_api_connect();
	$invoice = localAPI('getinvoice',array('invoiceid'=> $_REQUEST['invoice_id']),(int)gefip_setup_admin()['id']);
	if( $invoice['invoiceid']){
		$qrcode = gefip_get_local_qrc($_REQUEST['invoice_id']);	
		$charge = gefip_charge_verify($qrcode['charge_id']);
		if(($charge['result']['status'] === 'paid') and $invoice['status'] !== 'Paid' and (float)$invoice['total'] === (float)($charge['result']['total_cents']/100)){
			$add_trans = gefip_add_trans($invoice['userid'],$_REQUEST['invoice_id'], (float)number_format( $charge['result']['total_paid_cents']/100,  2, '.', ''), (float)number_format($charge['result']['taxes_paid_cents']/100,  2, '.', ''), 'gefip-'.$params_api['api_mode'].'-'.$qrcode['charge_id'], 'Pix pago - confirmação ao acessar a fatura');			
		}
		if($charge['result']['status']){
			echo $charge['result']['status'];
		}
	}
	if($params['log']){
		logModuleCall('gofasefipix','callback',array('request'=>$_REQUEST),'', array( 'charge'=>$charge ) );
	}
}
add_hook("AfterCronJob",1,"gefip_check_status_updates");
add_hook("EmailPreSend",1,"gefip_qrcode_mergetags");
add_hook("EmailTplMergeFields",1,"gefip_qrcode_mergetags_fields");