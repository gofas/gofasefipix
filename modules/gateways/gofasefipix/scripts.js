/**
 * Módulo Gofas Efi Pix para WHMCS
 * @copyright	2023 Gofas Software
 * @see			https://gofas.net/?p=15590
 * @license		https://gofas.net/?p=9340
 * @version		1.0.0
 */
$(document).ready(function () {
    var system_url = $("#system_url").val();
    var invoice_id = $("#invoice_id").val();
    var get_url = "modules/gateways/gofasefipix.php";
    setInterval(function () {
        $.get(
            system_url + get_url,
            { invoice_id: invoice_id },
            function (data) {
                /*
                if (data != "paid") {
                   console.log('status: ' + data);
                }
                */
                if (data == "paid") {
                    window.location.reload();
                }
            }
        );
    }, 1000); // Every 1 second
});