<?php
/**
 * Módulo Efí Pix para WHMCS
 * @copyright	2023 Gofas Software
 * @see			https://gofas.net/?p=15590
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14299
 * @version		1.2.0
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
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
				'clientid' => $params['clientidsandbox'],
				'clientsecret' => $params['clientsecretsandbox'],
				'certificate' => $params['certificatesandbox'],
				'pixkey'=> $params['pixkey'],
				'charge_url' => 'https://pix-h.api.efipay.com.br',
			];
		}
		if(!$params['sandbox']){
			$params_api = [
				'api_mode' => 'live',
				'clientid' => $params['clientid'],
				'clientsecret' => $params['clientsecret'],
				'certificate' => $params['certificate'],
				'pixkey'=> $params['pixkey'],
				'charge_url' => 'https://pix.api.efipay.com.br',
			];
		}
		return $params_api;
	}
}

if(!function_exists('gefip_verify_install')){
	function gefip_verify_install(){
		if(!Capsule::schema()->hasTable('gofasefipix') ){
			try {
				Capsule::schema()->create('gofasefipix', function($table){
					$table->string('invoice_id');
					$table->string('id');
					$table->string('txid');
					$table->string('amount');
					$table->text('qrcode');
					$table->text('qrcode_image');
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
    		$module_version	= '1.2.0';
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
    					'Value' => 'Gofas Efí Pix',
    				],
    				'separator_1' => [
    					'Description' => '
    					<div>
    						<div style="float: right; padding: 0px;">
    						'.gefip_decrypt($check_updates['check']).'
    						</div>
    						<div>
    							<h4 style="padding-top: 5px; color: red;">Módulo Gofas Efí Pix para WHMCS v'.$module_version.' | requer WHMCS versão 8.6.1 ou superior</h4>
    							'.$check_updates['message'].'
    							<p><a style="text-decoration:underline;" target="_blank" href="https://gofas.net/?p=15590#configuration">Documentação do módulo</a> | <a style="text-decoration:underline;" target="_blank" href="https://dev.efi.com/reference/metadados/">Documentação da API efi</a></p>
								
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
    				'Value' => 'Gofas Efí Pix',
    			),
    			'separator_1' => array(
    				'Description' => '
    				<div>
    					<div style="float: right; padding: 0px;">
    					'.gefip_decrypt($check_updates['check']).'
    					</div>
    					<div>
    						<h4 style="padding-top: 5px;">Módulo Gofas Efí Pix para WHMCS v'.$module_version.'</h4>
    						'.$check_updates['message'].'
    						<p><a style="text-decoration:underline;" target="_blank" href="https://gofas.net/?p=15590#configuration">Documentação do módulo</a> | <a style="text-decoration:underline;" target="_blank" href="https://dev.efi.com/reference/metadados/">Documentação da API efi</a></p>
    					</div>
    				</div>',
    			),
    		// Client ID
			'clientid' => array(
				'FriendlyName' => $opt_num++.'- Chave Client ID Produção<span class="gefip_required">*</span>',
				'Type' => 'text',
				'Size' => '40',
				'Default' => '',
				'Description' => '<span class="gefip_required_txt">(Obrigatório)</span>',
			),
			// Client Secret
			'clientsecret' => array(
				'FriendlyName' => $opt_num++.'- Chave Client Secret Produção<span class="gefip_required">*</span>',
				'Type' => 'text',
				'Size' => '40',
				'Default' => '',
				'Description' => '<span class="gefip_required_txt">(Obrigatório)</span>',
			),
			// Certificate
			'certificate' => array(
				'FriendlyName' => $opt_num++.'- Certificado Produção<span class="gefip_required">*</span>',
				'Type' => 'text',
				'Size' => '40',
				'Default' => '',
				'Description' => '<span class="gefip_required_txt">(Obrigatório)</span>. Caminho completo e nome do arquivo, exemplo: /var/www/site.com.br/certificado.pem',
			),
			// Client ID Sandbox
			'clientidsandbox' => array(
				'FriendlyName' => $opt_num++.'- Chave Client ID Desenvolvimento<span class="gefip_required">*</span>',
				'Type' => 'text',
				'Size' => '40',
				'Default' => '',
				'Description' => '<span class="gefip_required_txt">(Obrigatório)</span>',
			),
			// Client Secret Sandbox
			'clientsecretsandbox' => array(
				'FriendlyName' => $opt_num++.'- Chave Client Secret Desenvolvimento<span class="gefip_required">*</span>',
				'Type' => 'text',
				'Size' => '40',
				'Default' => '',
				'Description' => '<span class="gefip_required_txt">(Obrigatório)</span>',
			),
			// Certificate
			'certificatesandbox' => array(
				'FriendlyName' => $opt_num++.'- Certificado Homologação<span class="gefip_required">*</span>',
				'Type' => 'text',
				'Size' => '40',
				'Default' => '',
				'Description' => '<span class="gefip_required_txt">(Obrigatório)</span>. Caminho completo e nome do arquivo, exemplo: /var/www/site.com.br/certificado.pem',
			),
			// Chave Pix
			'pixkey' => array(
				'FriendlyName' => $opt_num++.'- Chave Pix<span class="gefip_required">*</span>',
				'Type' => 'text',
				'Size' => '40',
				'Default' => '',
				'Description' => '<span class="gefip_required_txt">(Obrigatório)</span>.Chave Pix aleatória registrada na sua conta Efí (<a target="_blank" style="text-decoration: underline;" href="https://app.sejaefi.com.br/pix/minhas-chaves">gerenciar chaves</a>)',
			),
    			'separator_2' => array(
    				'Description' => '<span><a target="_blank" style="text-decoration:underline;" href="https://dev.efi.com/reference/autentica%C3%A7%C3%A3o#criando-suas-chaves-de-api-api-tokens-via-painel">Veja aqui como criar suas chaves de API (API Tokens) via painel Efí</a></span>',
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
    				'Description' => 'Salva informações de diagnóstico em <a target="_blank" style="text-decoration: underline;" href="'.$whmcs_url['admin_url'].'/systemmodulelog.php">Utilitários > Logs > Log de Módulo</a>. Para funcionar, antes é necessário ativar o debug de módulo clicando em "Ativar Log de Debug". <a target="_blank" style="text-decoration: underline; color: red;" href="'.$whmcs_url['admin_url'].'/systemmodulelog.php">VER LOG</a>.',
    			),
    			// minimum amount
    			'minimunamount' => array(
    				'FriendlyName' => $opt_num++.'- Valor mínimo',
    				'Type' => 'text',
    				'Size' => '10',
    				'Default' => '0.01',
    				'Description' => 'Insira o valor total mínimo da fatura para permitir pagamento via Pix. Formato: Decimal, separado por ponto. Não deve ser menor que o valor da tarifa aplicada à sua conta efi.',
    			),
				'fee' => array(
					'FriendlyName' => $opt_num++.'- Valor da tarifa Efí',
					'Type' => 'text',
					'Default' => '0.99',
					'Size' => '10',
					'Description'    => '<span class="gefic_optional_txt">(Opcional)</span> Insira o valor percentual da comissão paga à Efí a cada transação via Pix com pagamento confirmado. Essa informação servirá para calcular e preencher o campo "Taxas" (fee) da lista de transações do WHMCS, já que a API Efí  não retorna essa informação. Use ponto(.) para separar casas decimais, ex.: 1.5',
				),
    			// Top billet button message 
    			'message' => array(
    				'FriendlyName' => $opt_num++.'- Mensagem na fatura',
    				'Type' => 'text',
    				'Size' => '50',
    				'Default' => 'Escaneie ou copie e cole o código:',
    				'Description' => 'Texto exibido na fatura acima do botão "Vizualizar Pix"',
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
			$gefip_config = array_merge($renderize,$footer);
		}
    	return $gefip_config;
    }
}
if(!function_exists('gofasefipix_link')){
    function gofasefipix_link($params){
		if(stripos($_SERVER['REQUEST_URI'], 'viewinvoice') !== false ){
			$gefip_webhook = gefip_webhook();
			$log['webhook'] = $gefip_webhook;
		}
    	if(stripos($_SERVER['REQUEST_URI'], 'viewinvoice') !== false ){
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
				//$result .= '<script type="text/javascript" src="'.gefip_whmcs_url('whmcs_url').'/modules/gateways/gofasefipix/scripts.js" charset="UTF-8"></script>';
				$result .= '<script>
				$(document).ready(function () {
					var system_url = $("#system_url").val();
					var invoice_id = $("#invoice_id").val();
					var get_url = "modules/gateways/gofasefipix.php";
					setInterval(function () {
						$.get(
							system_url + get_url,
							{ invoice_id: invoice_id },
							function (data) {
								if (data == "CONCLUIDA") {
									window.location.reload();
								}
							}
						);
					}, 1000); // Every 1 second
				});
				
				</script>';
    			$params_api = gefip_api_connect();
				
    			$customer = gefip_customer($params['clientdetails']['id']);
    			$log['customer'] = $customer;
    			$saved_qrcode = gefip_get_local_qrc($params['invoiceid']);
    			
				$GetInvoiceResults			= localAPI('getinvoice',array('invoiceid'=>$params['invoiceid'] ), (int)gefip_setup_admin()['id'] );
    			
    			if($saved_qrcode['qrcode'] and (float)$saved_qrcode['amount'] === (float)$params['amount'] and strtotime("now -1 hour") < strtotime($saved_qrcode['updated_at']) ){
    				$charge_verify = gefip_charge_verify($saved_qrcode['txid']);
    				$log['charge_verify'] = $charge_verify;
    				if((string)$charge['result']['status'] === (string)'CONCLUIDA'){
    					$add_trans = gefip_add_trans($params['clientdetails']['id'],$params['invoiceid'], (float)$charge['result']['valor']['original'], gefip_fee($charge['result']['valor']['original']), 'gefip-'.$params_api['api_mode'].'-'.$qrcode['txid'], 'Pix pago - confirmação ao acessar a fatura');
						header_remove();
    					header("Location: ".gefip_whmcs_url('whmcs_url').'/viewinvoice.php?id='.$params['invoiceid'],true,303);
    					exit;
    				}
    				$result .= $params['message'];
					$result .= '<img style="width: 200px;border: 1px solid #ccc;" src="'.$saved_qrcode['qrcode_image'].'">';
    				$result .= '<input value="'.$saved_qrcode['qrcode'].'" id="qrcodeforcopy" style="width: 0px;height: 0px;font-size: 0px;padding: 0px;display:none;">';
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
    			if(!$saved_qrcode['qrcode'] || !$saved_qrcode['qrcode_image'] || (float)$saved_qrcode_amount !== (float)$params['amount']){
    				$line_items = array();
    				foreach( $GetInvoiceResults['items']['item'] as $Value){
						$increment = 0;
    					$line_items[$increment++] = $Value['description'];
    				}
					if($customer['cpf']){
						$postfields = [
							'calendario'=> [
							  'expiracao'=> 3600
							],
							'devedor'=> [
							  'cpf'=> $customer['cpf'],
							  'nome'=> $customer['name']
							],
							'valor'=> [
							  'original'=> number_format($params['amount'], 2,'.',''),
							],
							'chave'=> $params['pixkey'],
							'solicitacaoPagador'=> (string)(substr( implode("\n",$line_items),  0, 140))
						];
					}
					if($customer['cnpj']){
						$postfields = [
							'calendario'=> [
							  'expiracao'=> 3600
							],
							'devedor'=> [
							  'cnpj'=> $customer['cnpj'],
							  'nome'=> $customer['name']
							],
							'valor'=> [
							  'original'=> number_format($params['amount'], 2,'.',''),
							],
							'chave'=> $params['pixkey'],
							'solicitacaoPagador'=> (string)(substr( implode("\n",$line_items),  0, 140))
						];
					}
    				$qrcode_ = gefip_charge($postfields);
					if((int)$qrcode_['result_code'] !== (int)200){
    					$error .= $qrcode_['result_code'].': ';
    					if(is_array($qrcode_['result']['errors'])){
							foreach($qrcode_['result']['errors'] as $key=>$value){
    							$error .= $key.' '.implode(", ",$value);
    						}
						}
    				}
    				$log['postfields_json'] = json_encode($postfields);
    				$log['qrcode_'] = $qrcode_;
    				if($qrcode_['result']['qrcode']){
                        if(!$saved_qrcode['qrcode'] || !$saved_qrcode['qrcode_image']){
    						$save_qrc = gefip_save_qrc(
    							[
    								'invoice_id'=>$params['invoiceid'],
    								'id'=>$qrcode_['result']['id'],
									'txid'=>$qrcode_['result']['cob']['txid'],
    								'amount'=>$params['amount'],
    								'qrcode'=>$qrcode_['result']['qrcode'],
    								'qrcode_image'=>$qrcode_['result']['imagemQrcode'],
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
    								'id'=>$qrcode_['result']['id'],
									'txid'=>$qrcode_['result']['cob']['txid'],
    								'amount'=>$params['amount'],
    								'qrcode'=>$qrcode_['result']['qrcode'],
    								'qrcode_image'=>$qrcode_['result']['imagemQrcode'],
    								'api_mode'=>$params_api['api_mode'],
    							]
    						);
    						//$update_qrc = gefip_update_qrc($update_qrc);
    						if($update_qrc !== 'success'){
    							$error .= $update_qrc;
    						}
    					}
    					$result .= $params['message'];
						$result .= '<img style="width: 200px;border: 1px solid #ccc;" src="'.$qrcode_['result']['imagemQrcode'].'">';
    					//$result .= '<a target="_blank" class="btn btn-default" style=" float: left;font-size: 14px;" href="'.$qrcode_['result']['pix']['qrcode'].'">Visualizar o Pix</a>';
    					$result .= '<input value="'.$qrcode_['result']['qrcode'].'" id="qrcodeforcopy" style="width: 0px;height: 0px;font-size: 0px;padding: 0px;display:none;">';
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
    	}
    }
}
if( !function_exists('gefip_get_token') ){
	function gefip_get_token(){
		$params_api = gefip_api_connect();
		$curl = curl_init($params_api['charge_url'].'/oauth/token');
		$client_id=$params_api['clientid'];
		$client_secret=$params_api['clientsecret'];
  		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Authorization: Basic '. base64_encode("$client_id:$client_secret"),
			'Content-Type: application/json',
			'partner-token: baaf5b95d55433890bd835cf006772b9462bde8f',
		));
  		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  		curl_setopt($curl, CURLOPT_POST, true);
  		curl_setopt($curl, CURLOPT_SSLCERT, $params_api['certificate']);
  		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array(
			'grant_type'=>'client_credentials',
			'partner_token'=>'baaf5b95d55433890bd835cf006772b9462bde8f',
		)));
  		$json = json_decode(curl_exec($curl), true);
		if($json['access_token']){
			return array('access_token'=>$json['access_token']);
		}
		else {
			if($json){
	  			$error	.= 'Erro: '.implode(', ', $json);
			}
			return array('error'=> $error, 'debug'=> $json);
		}
	}
}
if(!function_exists('gefip_charge')){
	function gefip_charge($postfields){
		$params_api = gefip_api_connect();
    	$access_token = gefip_get_token();
		$curl = curl_init($params_api['charge_url'].'/v2/cob');
  		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer '.$access_token['access_token'],
			'Content-Type: application/json',
			'partner-token: baaf5b95d55433890bd835cf006772b9462bde8f'));
  		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_SSLCERT, $params_api['certificate']);
  		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postfields));
		$result_ = json_decode(curl_exec($curl),true);
		$result_code_ = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if($result_['txid']){
			$curl = curl_init($params_api['charge_url'].'/v2/loc/'.$result_['loc']['id'].'/qrcode');
  			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				'Authorization: Bearer '.$access_token['access_token'],
				'Content-Type: application/json',
				'partner-token: baaf5b95d55433890bd835cf006772b9462bde8f'));
  			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_SSLCERT, $params_api['certificate']);
			$result = json_decode(curl_exec($curl),true);
			$result['id']=$result_['loc']['id'];
			$result['cob']=$result_;
			$result_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			curl_close($curl);
		}
		return ['result_code'=>$result_code,'result'=>$result];
	}
}
if(!function_exists('gefip_charge_verify')){
	function gefip_charge_verify($id){
		$params_api = gefip_api_connect();
		$access_token = gefip_get_token();
		$curl = curl_init($params_api['charge_url'].'/v2/cob/'.$id);
  			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				'Authorization: Bearer '.$access_token['access_token'],
				'Content-Type: application/json',
				'partner-token: baaf5b95d55433890bd835cf006772b9462bde8f'));
  			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_SSLCERT, $params_api['certificate']);
			$result = json_decode(curl_exec($curl),true);
			$result_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			curl_close($curl);
		return ['result_code'=>$result_code,'result'=>$result];
	}
}
if(!function_exists('gefip_webhook')){
	function gefip_webhook(){
		$params_api = gefip_api_connect();
		$access_token = gefip_get_token();
		$params = getGatewayVariables('gofasefipix');
		foreach( Capsule::table('tblconfiguration')->where('setting','=','gefip_webhook')->get(['value','created_at']) as $webhook_ ){
			$webhook				= json_decode($webhook_->value, true);
			$webhook['created_at']	= $webhook_->created_at;
		}
		$webhook_url = gefip_whmcs_url('whmcs_url').'modules/gateways/gofasefipix/includes';
		if($webhook['webhook_url'] !== $webhook_url || $webhook['pixkey'] !== $params['pixkey'] || !$webhook['webhook_url'] || !$webhook['pixkey']){
			$curl = curl_init($params_api['charge_url'].'/v2/webhook/'.$params['pixkey']);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				'Authorization: Bearer '.$access_token['access_token'],
				'Content-Type: application/json',
				'partner-token: baaf5b95d55433890bd835cf006772b9462bde8f',
				'x-skip-mtls-checking: true',));
  			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_SSLCERT, $params_api['certificate']);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['webhookUrl'=>$webhook_url]));
			$result = json_decode(curl_exec($curl),true);
			$result_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			curl_close($curl);
		}
		if((int)$result_code === 200){
			if(!empty($webhook['webhook_url']) || !empty($webhook['pixkey'])){
				try {
					Capsule::table('tblconfiguration')->where('setting','gefip_webhook')->update([
						'value' => json_encode([
							'webhook_url'=>$webhook_url,
							'pixkey'=>$params['pixkey'],
						]),
						'created_at' => $webhook['created_at'],
						'updated_at' => date("Y-m-d H:i:s")]
					);
				}
				catch (\Exception $e){
					$error .= $e->getMessage();
				}
			}
			else{
				try { Capsule::table('tblconfiguration')->insert(array(
					'setting' => 'gefip_webhook',
					'value' => json_encode([
						'webhook_url'=>$webhook_url,
						'pixkey'=>$params['pixkey'],
					]),
					'created_at' => date("Y-m-d H:i:s"),
					'updated_at' => date("Y-m-d H:i:s")
				));
				}
				catch (\Exception $e){
					$error .= $e->getMessage();
				}
			}
		}
		return ['webhook_url'=>$webhook_url,'result_code'=>$result_code,'result'=>$result,'error'=>$error];
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

if(!function_exists('gefip_add_trans')){
	function gefip_add_trans( $user_id, $invoice_id, $amount, $fee, $id, $description ){
		$params = getGatewayVariables('gofasefipix');
 		$addtransvalues['userid'] = $user_id;
 		$addtransvalues['invoiceid'] = $invoice_id;
 		$addtransvalues['description'] = $description;
 		$addtransvalues['amountin'] = $amount;
 		$addtransvalues['fees'] = $fee;
 		$addtransvalues['paymentmethod'] = 'gofasefipix';
 		$addtransvalues['transid'] = $id;
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
		if($cpf){
			$customer['cpf']=$cpf;
		}
		if($cnpj){
			$customer['cnpj']=$cnpj;
		}
		return $customer;
	}
}
if(!function_exists('gefip_save_qrc')){
	function gefip_save_qrc($qr_code){
		$data = array(
			'invoice_id'=>$qr_code['invoice_id'],
			'id'=>$qr_code['id'],
			'txid'=>$qr_code['txid'],
			'amount'=>$qr_code['amount'],
			//'duedate'=>$qr_code['duedate'],
			'qrcode'=>$qr_code['qrcode'],
			'qrcode_image'=>$qr_code['qrcode_image'],
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
			$message = '<p style="color: green"><i class="fas fa-check-square"></i> Você está executando a versão mais recente desse módulo</p>';
            $message .= '<p>Última verificação '.date('d/m/Y à\s H:i', strtotime($updated_at)).' - <a style="text-decoration:underline;" href="'.gefip_whmcs_url('admin_url').'/configgateways.php?manage=gofasefipix&resetversion=gofasefipix#m_gofasefipix">verificar agora</a>.</p>';
		}
		if( $available_version_int > $module_version_int ){
			$message = '<p style="font-size: 14px; color: red;"><i class="fas fa-exclamation-triangle"></i> Atualização disponível, verifique a <a style="color:#CC0000;text-decoration:underline;" href="https://gofas.net/?p='.$page_id.'" target="_blank">versão '.$available_version.'</a>. Última verificação '.date('d/m/Y H:i', strtotime($updated_at)).'.';
            $message .= '<p>Última verificação '.date('d/m/Y à\s H:i', strtotime($updated_at)).' - <a style="text-decoration:underline;" href="'.gefip_whmcs_url('admin_url').'/configgateways.php?manage=gofasefipix&resetversion=gofasefipix#m_gofasefipix">verificar agora</a>.</p>'; #9
		}
		if( $available_version_int < $module_version_int ){
			$message = '<p style="font-size: 14px; color: orange;"><i class="fas fa-exclamation-triangle"></i> Você está executando uma versão Beta desse módulo<br>Baixar versão estável: <a style="color:#CC0000;text-decoration:underline;" href="https://gofas.net/?p='.$page_id.'" target="_blank">v'.$available_version.'</a>. Última verificação '.date('d/m/Y H:i', strtotime($updated_at)).'.';
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

if(!function_exists('gefip_get_protected_property')){
	function gefip_get_protected_property($object, $property){
	    $reflectedClass = new \ReflectionClass($object);
	    $reflection = $reflectedClass->getProperty($property);
	    $reflection->setAccessible(true);
	    return $reflection->getValue($object);
	}
}

if(!function_exists('gefip_fee')){
    function gefip_fee($amount){
		$params = getGatewayVariables('gofasefipix');
		$fee = (float)(((float)$amount/100)*(float)$params['fee']);
		if($fee > (float)'7.90'){
			return (float)'7.90';
		}
		if($fee <= (float)'7.90'){
			return $fee;
		}
	}
}
if($_REQUEST['invoice_id']){
	$params = getGatewayVariables('gofasefipix');
	$params_api = gefip_api_connect();
	$invoice = localAPI('getinvoice',array('invoiceid'=> $_REQUEST['invoice_id']),(int)gefip_setup_admin()['id']);
	if( $invoice['invoiceid']){
		$qrcode = gefip_get_local_qrc($_REQUEST['invoice_id']);	
		$charge = gefip_charge_verify($qrcode['txid']);
		if(((STRING)$charge['result']['status'] === (STRING)'CONCLUIDA') and $invoice['status'] !== 'Paid' and (float)$invoice['total'] === (float)$charge['result']['valor']['original']){
			$add_trans = gefip_add_trans($invoice['userid'],$_REQUEST['invoice_id'], (float)$charge['result']['valor']['original'], gefip_fee($charge['result']['valor']['original']), 'gefip-'.$params_api['api_mode'].'-'.$qrcode['txid'], 'Pix pago - confirmação enquanto o cliente visualizava a fatura');			
		}
		if($charge['result']['status']){
			echo $charge['result']['status'];
		}
	}
	if($params['log']){
		logModuleCall('gofasefipix','callback',array('request'=>$_REQUEST),'', array( 'charge'=>$charge ) );
	}
}
if($_POST['id']){
	$params = getGatewayVariables('gofasefipix');
	$params_api = gefip_api_connect();
	$invoice = localAPI('getinvoice',array('invoiceid'=> $_POST['id']),(int)gefip_setup_admin()['id']);
	if( $invoice['invoiceid']){
		$qrcode = gefip_get_local_qrc($_POST['id']);	
		$charge = gefip_charge_verify($qrcode['txid']);
		if(((string)$charge['result']['status'] === (string)'CONCLUIDA') and $invoice['status'] !== 'Paid' and (float)$invoice['total'] === (float)$charge['result']['valor']['original']){
			$add_trans = gefip_add_trans($invoice['userid'],$_POST['id'], (float)$charge['result']['valor']['original'], gefip_fee($charge['result']['valor']['original']), 'gefip-'.$params_api['api_mode'].'-'.$qrcode['txid'], 'Pix pago - confirmação via webook /viewinvoice.php');			
		}
		if($charge['result']['status']){
			echo $charge['result']['status'];
		}
	}
	if($params['log']){
		logModuleCall('gofasefipix','post_1',array('request'=>$_POST),'', array( 'charge'=>$charge ) );
	}
}