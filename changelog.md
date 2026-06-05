# Gofas Efí Pix

Módulo de gateway de pagamento para WHMCS que integra cobranças via Pix instantâneo através da API Efí (EFI Pay). Desenvolvido pela Gofas Software.

## Funcionalidades

- Geração de cobranças Pix via API Efí
- QR Code dinâmico exibido na área do cliente
- Baixa automática de fatura via webhook
- Suporte a Pix com vencimento

## Requisitos

- WHMCS 7.x ou superior
- PHP 8.x
- Conta Efí (EFI Pay) com Pix habilitado
- Credenciais: Client ID, Client Secret e certificado `.p12`

## Instalação

1. Copiar `modules/gateways/` para o `modules/gateways/` do WHMCS
2. Ativar em **Configurações > Formas de Pagamento**
3. Informar credenciais e certificado

## Changelog

Ver [changelog.md](changelog.md).
