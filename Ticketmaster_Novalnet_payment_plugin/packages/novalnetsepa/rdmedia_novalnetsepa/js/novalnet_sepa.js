/*
 * Novalnet Direct Debit SEPA Script
 * By Novalnet AG (https://www.novalnet.de)
 * Copyright (c) Novalnet
 */
var nnsepa = jQuery.noConflict();
nnsepa(document).ready(function(){
    nnsepa('#elv_sepa_form').click(function(){
        if(nnsepa('#nn_sepa_bank_country').val() == ''){
            alert(nnsepa('#nn_sepa_country_error').val());
            unset_generated_hash_related_elements();
            return false;
        }
        if(!nnsepa('#nnsepa_mandate_confirm').prop('checked')){
            alert(nnsepa('#nn_sepa_iban_bic_error').val());
            unset_generated_hash_related_elements();
            return false;
        }
    });
});

function generate_sepa_iban_bic(value) {

    if (!(document.getElementById('nnsepa_mandate_confirm').checked)) {
        unset_generated_hash_related_elements();
        document.getElementById('nnsepa_mandate_confirm').checked = false;
        return false;
    }
    var bank_country = "";
    var account_holder = "";
    var account_no = "";
    var bank_code = "";
    var nn_sepa_uniqueid = "";
    var nn_vendor = "";
    var nn_auth_code = "";

    if (document.getElementById('nn_sepa_bank_country')) {
        bank_country = document.getElementById('nn_sepa_bank_country').value;
    }
    if (document.getElementById('nnsepa_holder')) {
        account_holder = removeUnwantedSpecialChars(document.getElementById('nnsepa_holder').value).trim();
    }
    if (document.getElementById('nnsepa_account_number')) {
        account_no = removeUnwantedSpecialChars(document.getElementById('nnsepa_account_number').value);
    }
    if (document.getElementById('nnsepa_bank_code')) {
        bank_code = removeUnwantedSpecialChars(document.getElementById('nnsepa_bank_code').value);
    }
    if (document.getElementById('nn_vendor')) {
        nn_vendor = getNumbersOnly(document.getElementById('nn_vendor').value);
    }
    if (document.getElementById('nn_auth_code')) {
        nn_auth_code = document.getElementById('nn_auth_code').value;
    }
    if (document.getElementById('nn_sepa_unique')) {
        nn_sepa_uniqueid = document.getElementById('nn_sepa_unique').value;
    }
    document.getElementById('sepaiban').value = '';
    document.getElementById('sepabic').value = '';
    account_no = account_no.trim();
    bank_code = bank_code.trim();
    console.log(account_holder,account_no,bank_code,nn_vendor,nn_auth_code,bank_country,nn_sepa_uniqueid);
    if (isNaN(account_no) && isNaN(bank_code)) {
        nnsepa('#novalnet_sepa_iban_span').html('');
        nnsepa('#novalnet_sepa_bic_span').html('');
        generate_sepa_hash();
        return false;
    }

    if (bank_country == '') {
        alert(document.getElementById('nn_sepa_country_error').value);
        unset_generated_hash_related_elements();
        return false;
    }


    if ((account_no != '' && bank_code != '') && (isNaN(account_no) && !isNaN(bank_code)) || (!isNaN(account_no) &&
		isNaN(bank_code))) {
        alert(nnsepa('#nn_sepa_account_error').val());
        unset_generated_hash_related_elements();
        return false;
    }
    if ((bank_code == '' || !isNaN(bank_code)) && isNaN(account_no)) {
        generate_sepa_hash();
        return false;
    }

    if (nn_vendor == '' || nn_auth_code == '') {
        alert(document.getElementById('nn_sepa_account_error').value);
        return false;
    }



    if (account_holder == '' || account_no == '' || bank_code == '' || nn_vendor == '' || nn_auth_code == '' || nn_sepa_uniqueid == '') {

        alert(document.getElementById('nn_sepa_account_error').value);
        unset_generated_hash_related_elements();
        return false;
    }
    var nnurl_val = {
        "account_holder": account_holder,
        "bank_account": account_no,
        "bank_code": bank_code,
        "vendor_id": nn_vendor,
        "vendor_authcode": nn_auth_code,
        "bank_country": bank_country,
        "unique_id": nn_sepa_uniqueid,
        "get_iban_bic": 1
    };
    sendRequest(nnurl_val, 'iban');
}

function generate_sepa_hash() {

    var bank_country = "";
    var account_holder = "";
    var account_no = "";
    var nn_sepa_iban = "";
    var nn_sepa_bic = "";
    var iban = "";
    var bic = "";
    var bank_code = "";
    var nn_sepa_uniqueid = "";
    var nn_vendor = "";
    var nn_auth_code = "";
    var mandate_confirm = 0;
    if (document.getElementById('nn_sepa_bank_country')) {
        bank_country = document.getElementById('nn_sepa_bank_country').value;
    }
    if (document.getElementById('nnsepa_holder')) {
        account_holder = removeUnwantedSpecialChars(document.getElementById('nnsepa_holder').value).trim();
    }
    if (document.getElementById('nnsepa_account_number')) {
        iban = removeUnwantedSpecialChars(document.getElementById('nnsepa_account_number').value);
    }
    if (document.getElementById('nnsepa_bank_code')) {
        bic = removeUnwantedSpecialChars(document.getElementById('nnsepa_bank_code').value);
    }
    if (document.getElementById('nn_sepa_iban')) {
        nn_sepa_iban = document.getElementById('nn_sepa_iban').value;
    }
    if (document.getElementById('nn_sepa_bic')) {
        nn_sepa_bic = document.getElementById('nn_sepa_bic').value;
    }
    if (document.getElementById('nn_vendor')) {
        nn_vendor = getNumbersOnly(document.getElementById('nn_vendor').value);
    }
    if (document.getElementById('nn_auth_code')) {
        nn_auth_code = document.getElementById('nn_auth_code').value;
    }
    if (document.getElementById('nn_sepa_unique')) {
        nn_sepa_uniqueid = document.getElementById('nn_sepa_unique').value;
    }
    if (nn_vendor == '' || nn_auth_code == '') {
        alert(nnsepa('#nn_sepa_account_error').val());
        unset_generated_hash_related_elements();
        return false;
    }
    iban = iban.trim();
    bic = bic.trim();

    if (bank_country == '') {
        alert(document.getElementById('nn_sepa_country_error').value);
        unset_generated_hash_related_elements();
        return false;
    }
    if (account_holder == '' || iban == '' || nn_vendor == '' || nn_auth_code == '' || nn_sepa_uniqueid == '') {
        alert(nnsepa('#nn_sepa_account_error').val());
        unset_generated_hash_related_elements();
        return false;
    }
    if (bank_country != 'DE' && bic == '') {
        alert(nnsepa('#nn_sepa_account_error').val());
        unset_generated_hash_related_elements();
        return false;
    } else if (bank_country == 'DE' && !isNaN(iban) && bic == '') {
        alert(nnsepa('#nn_sepa_account_error').val());
        unset_generated_hash_related_elements();
        return false;
    }
    if (bank_country == 'DE' && (bic == '' || !isNaN(bic)) && isNaN(iban)) {
        bic = '123456';
    }
    if (!isNaN(iban) && !isNaN(bic)) {
        account_no = iban;
        bank_code = bic;
        iban = bic = '';
    }
    if (nn_sepa_iban != '' && nn_sepa_bic != '') {
        iban = nn_sepa_iban;
        bic = nn_sepa_bic;
    }
    var nnurl_val = {
        "account_holder": account_holder,
        "bank_account": account_no,
        "bank_code": bank_code,
        "vendor_id": nn_vendor,
        "vendor_authcode": nn_auth_code,
        "bank_country": bank_country,
        "unique_id": nn_sepa_uniqueid,
        "sepa_data_approved": 1,
        "mandate_data_req": 1,
        "iban": iban,
        "bic": bic
    };

    sendRequest(nnurl_val, 'hash');
}

function sendRequest(nnurl_val, type) {

    document.getElementById('loader').style.display = 'block';
    document.getElementById('nnsepa_mandate_confirm').disabled = true;
    var nnurl = "https://payport.novalnet.de/sepa_iban";
    if ('XDomainRequest' in window && window.XDomainRequest !== null) {
        var xdr = new XDomainRequest();
        xdr.open('GET', nnurl);
        xdr.onload = function() {
            var data = JSON.parse(this.responseText);
            if (data.hash_result == 'success') {
                processResponse(data, type);
            }
        };
        xdr.send(nnurl_val);
    } else {
        jQuery.ajax({
            type: 'POST',
            url: nnurl,
            data: nnurl_val,
            dataType: 'json',
            success: function(data) {
                if (data.hash_result == "success") {
                    processResponse(data, type);
                }
            }
        });
    }
}

function processResponse(data, type) {
    switch (type) {
        case 'refill':
            hash_string = data.hash_string;
            sepaFormRefill(hash_string);
            break;
        case 'hash':
            document.getElementById('nn_sepa_hash').value = data.sepa_hash;
            break;
        case 'iban':
            if (data.IBAN == '' || data.BIC == '') {
                alert(nnsepa('#nn_sepa_account_error').val());
                unset_generated_hash_related_elements();
                return false;
            }
            document.getElementById('nn_sepa_iban').value = data.IBAN;
            document.getElementById('nn_sepa_bic').value = data.BIC;

            if (data.IBAN != '' && data.BIC != '') {
                nnsepa('#novalnet_sepa_iban_span').css('display', 'block');
                nnsepa('#novalnet_sepa_bic_span').css('display', 'block');
                nnsepa('#novalnet_sepa_iban_span').html('<b>IBAN:</b> ' + data.IBAN);
                nnsepa('#nn_sepa_overlay_iban_tr').show(60);
            } else {
                nnsepa('#nn_sepa_overlay_iban_tr').hide(60);
            }
            if (data.BIC != '') {
                nnsepa('#novalnet_sepa_bic_span').html('<b>BIC:</b> ' + data.BIC);
                nnsepa('#nn_sepa_overlay_bic_tr').show(60);
            } else {
                jQuery('#nn_sepa_overlay_bic_tr').hide(60);
            }
            generate_sepa_hash();
            return true;
            break;
    }
    document.getElementById('nnsepa_mandate_confirm').disabled = false;
    document.getElementById('loader').style.display = 'none';
}

function sepaFormRefill(hash_string) {

    var acc_holder = String(hash_string).match("account_holder=(.*)&bank_code");
    document.getElementById('nnsepa_holder').value = acc_holder[1];
    hash_string = hash_string.split('&');
    for (var i = 0; i < hash_string.length; i++) {
        var hash_result_val = hash_string[i].split('=');
        switch (hash_result_val[0]) {
            case 'bank_country':
                document.getElementById('nn_sepa_bank_country').value = hash_result_val[1];
                break;
            case 'iban':
                document.getElementById('nnsepa_account_number').value = hash_result_val[1];
                break;
            case 'bic':
                if (hash_result_val[1] != '123456')
                    document.getElementById('nnsepa_bank_code').value = hash_result_val[1];
                break;
        }
    }
}

function separefillformcall() {

    var refillpanhash = '';
    if (document.getElementById('nn_sepa_input_panhash')) {
        refillpanhash = document.getElementById('nn_sepa_input_panhash').value;
    }
    if (refillpanhash == '' || refillpanhash == 'undefined') {
        return false;
    }

    var nn_vendor = "";
    var nn_auth_code = "";
    var nn_uniqueid = "";
    if (document.getElementById('nn_vendor')) {
        nn_vendor = getNumbersOnly(document.getElementById('nn_vendor').value);
    }
    if (document.getElementById('nn_auth_code')) {
        nn_auth_code = document.getElementById('nn_auth_code').value;
    }
    if (document.getElementById('nn_sepa_unique')) {
        nn_uniqueid = document.getElementById('nn_sepa_unique').value;
    }
    if (nn_vendor == '' || nn_auth_code == '' || nn_uniqueid == '') {
        return false;
    }
    var nnurl_val = "vendor_id=" + nn_vendor + "&vendor_authcode=" + nn_auth_code + "&unique_id=" + nn_uniqueid + "&sepa_data_approved=1&mandate_data_req=1&sepa_hash=" + refillpanhash;
    sendRequest(nnurl_val, 'refill');
}


nnsepa(document).ready(function() {
    nnsepa('.nnsepa_mandate_confirm').mouseover(function() {
        nnsepa('.nnsepa_mandate_confirm').children('a').css({
            "background-color": "white",
            "color": "black"
        });
    });

    separefillformcall();

    nnsepa('#nnsepa_account_number').change(function() {
        unset_generated_hash_related_elements();
    });
    nnsepa('#nnsepa_bank_code').change(function() {
        unset_generated_hash_related_elements();
    });
    nnsepa('#nn_sepa_bank_country').change(function() {
        unset_generated_hash_related_elements();
    });
});

function getNumbersOnly(input_val) {
    return input_val = input_val.replace(/^\s+|\s+[^0-9]$/g, '');
}

function removeUnwantedSpecialChars(input_val) {
    return input_val.replace(/[\/\\|\]\[|#@,+()$~%`'":;*?<>!^{}=_]/g, '');
}

function unset_generated_hash_related_elements() {

    document.getElementById('loader').style.display = 'none';
    nnsepa('#novalnet_sepa_iban_span').html('');
    nnsepa('#novalnet_sepa_bic_span').html('');
    document.getElementById('nnsepa_mandate_confirm').disabled = false;
    document.getElementById('nnsepa_mandate_confirm').checked = false;
    document.getElementById('nn_sepa_hash').value = '';
    document.getElementById('nn_sepa_iban').value = '';
    document.getElementById('nn_sepa_bic').value = '';
}

function sepa_validate_account_number(event) {
    var keycode = ('which' in event) ? event.which : event.keyCode;
    var reg = /^(?:[A-Za-z0-9]+$)/;
    if (event.target.id == 'nnsepa_holder') var reg = /^(?:[A-Za-z&.-\s]+$)/;
    if (keycode >= 39 && keycode <= 44) {
        return false;
    }
    return (reg.test(String.fromCharCode(keycode)) || keycode == 0 || keycode == 8);
}
