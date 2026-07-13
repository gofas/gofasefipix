# Módulo Efí Pix para WHMCS

[![versão](https://img.shields.io/github/v/release/gofas/gofasefipix?label=vers%C3%A3o&color=005071&style=flat-square)](https://github.com/gofas/gofasefipix/releases/latest)
[![downloads](https://img.shields.io/github/downloads/gofas/gofasefipix/total?label=downloads&color=005071&style=flat-square)](https://github.com/gofas/gofasefipix/releases/latest)
[![licença](https://img.shields.io/badge/licen%C3%A7a-propriet%C3%A1ria-005071?style=flat-square)](https://gofas.net/contrato-de-venda-de-licenca-de-uso-de-software/)
[![suporte](https://img.shields.io/badge/suporte-f%C3%B3rum%20gratuito-ff8700?style=flat-square)](https://gofas.net/foruns/)

Receba por Pix instantaneamente, direto na sua conta Efí, as cobranças do WHMCS. O QR code e o copia e cola aparecem na própria fatura, e a confirmação do pagamento é automática, em tempo real. Desenvolvido pela Gofas Software, é 100% gratuito.

## Sumário

- [Download](#download)
- [Funcionalidades](#funcionalidades)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Configuração](#configuração)
- [Informações importantes](#informações-importantes)
- [Suporte](#suporte)
- [Licença](#licença)

## Download

**[Baixar a versão mais recente](https://github.com/gofas/gofasefipix/releases/latest/download/gofasefipix.zip)**

O download é contabilizado no site pelo contador de instalações do módulo.

## Funcionalidades

- **Confirmação automática em tempo real** do pagamento e baixa da fatura
- **QR code na fatura** do WHMCS, sem redirecionamentos
- **Copia e cola** do código Pix em um clique
- **Reemissão automática** do QR code expirado
- **Chave Pix definida** pelo administrador do WHMCS
- **Mensagem personalizada** exibida na fatura
- **Valor mínimo** da fatura para permitir pagamento via Pix
- **Cálculo da tarifa** por transação confirmada, preenchendo o campo "Taxas" (fee) da lista de transações do WHMCS
- **Dispensa configuração de campos CPF/CNPJ**: o módulo detecta automaticamente os campos personalizados de clientes
- **Suporte a produção e a testes (sandbox)**
- **Logs de diagnóstico** configuráveis
- **Aviso de atualização** e verificação de versão na própria tela de configuração do módulo

## Requisitos

- WHMCS >= 8.0
- PHP >= 7.1
- Conta Efí (Efí Pay) com Pix habilitado
- Credenciais: Client ID e Client Secret (produção e homologação)
- Certificado `.p12` convertido para `.pem` (produção e homologação)
- Chave Pix aleatória cadastrada na conta Efí

## Instalação

1. Baixe o arquivo pelo link de download e descompacte. Será criada a pasta `gofasefipix`.
2. Copie a pasta `modules` de dentro de `gofasefipix` para a raiz da instalação do WHMCS, mesclando com as pastas existentes.
3. Ative o módulo em `Opções > Pagamentos > Portais para Pagamentos > aba All Payment Gateways`.
4. Informe as credenciais, os certificados e a chave Pix.

## Configuração

### Pré configuração na Efí

1. Em `API > Aplicações`, crie uma aplicação e copie as credenciais Client ID e Client Secret geradas para produção e para homologação.
2. Em `API > Meus certificados`, gere um certificado para produção e outro para homologação.
3. Converta os certificados para o formato PEM (certificado e chave em um único arquivo):

```
openssl pkcs12 -in certificado.p12 -out certificado.pem -nodes -password pass:""
```

4. Em `Pix > Minhas chaves`, cadastre uma chave aleatória para usar na configuração do módulo.

### Pré configuração no WHMCS

Crie um campo personalizado de cliente para CPF e/ou CNPJ, ou dois campos distintos, um para cada documento. O módulo identifica os campos automaticamente. O CPF é obrigatório mesmo quando o cliente tem CNPJ no cadastro.

### Opções do módulo

<img src="https://raw.githubusercontent.com/gofas/gofasefipix/master/docs/img/tela-configuracoes-modulo-1.3.0.png" alt="Tela de configuracoes do modulo" width="640">

- **Chave Client ID Produção** e **Chave Client Secret Produção**: credenciais da aplicação em modo produção.
- **Certificado Produção**: caminho completo do arquivo `.pem` de produção, exemplo `/var/www/site.com.br/certificado.pem`.
- **Chave Client ID Desenvolvimento** e **Chave Client Secret Desenvolvimento**: credenciais da aplicação em modo homologação.
- **Certificado Homologação**: caminho completo do arquivo `.pem` de homologação.
- **Chave Pix**: chave Pix aleatória registrada na sua conta Efí.
- **Sandbox**: gera cobranças em modo de teste.
- **Salvar Logs**: grava informações de diagnóstico em `Utilitários > Logs > Log de Módulo`.
- **Valor mínimo**: valor mínimo da fatura para permitir pagamento via Pix. Formato decimal separado por ponto, exemplo `2.99`.
- **Valor da tarifa Efí**: percentual da comissão paga à Efí a cada transação confirmada, usado para preencher o campo "Taxas" (fee) da transação no WHMCS.
- **Mensagem na fatura**: texto exibido na fatura, acima do botão de visualizar o Pix.
- **Enviar estatísticas de uso (opcional)**: controla o envio identificado das estatísticas de confirmação de pagamento. Desmarcado, as confirmações continuam sendo contabilizadas de forma anônima.

## Informações importantes

- A tarifa do Pix é paga separadamente à Efí, conforme o plano da sua conta.
- O certificado precisa estar em formato PEM e acessível pelo PHP no servidor. Mantenha o arquivo fora da raiz pública do site.
- Sempre faça backup antes de mudar algo no seu sistema.

## Suporte

Fórum de suporte gratuito: https://gofas.net/foruns/

## Licença

Software proprietário da Gofas Software. O código é público apenas para transparência e consulta; isso não concede licença de uso, modificação ou redistribuição. É vedado modificar, redistribuir, sublicenciar ou realizar engenharia reversa sem autorização prévia por escrito. Veja [LICENSE](LICENSE) e o contrato completo em https://gofas.net/contrato-de-venda-de-licenca-de-uso-de-software/.
