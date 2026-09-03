import re

def patch_script(filename):
    with open(filename, 'r', encoding='utf-8') as f:
        content = f.read()

    # Fix togglePpInputs
    # Original: window.togglePpInputs = function() {
    # We want: window.togglePpInputs = async function() { ... if (cGpay) await load...
    if 'window.togglePpInputs = function() {' in content:
        old_func = """    window.togglePpInputs = function() {
        if ($('#ppCashCheck') && $('#ppCashGroup')) $('#ppCashGroup').style.display = $('#ppCashCheck').checked ? 'block' : 'none';
        if ($('#ppGPayCheck') && $('#ppGPayGroup')) $('#ppGPayGroup').style.display = $('#ppGPayCheck').checked ? 'block' : 'none';
        if ($('#ppPhonePeCheck') && $('#ppPhonePeGroup')) $('#ppPhonePeGroup').style.display = $('#ppPhonePeCheck').checked ? 'block' : 'none';
        if ($('#ppBankCheck') && $('#ppBankGroup')) $('#ppBankGroup').style.display = $('#ppBankCheck').checked ? 'block' : 'none';
        if ($('#ppCashCheck') && !$('#ppCashCheck').checked && $('#ppCashAmount')) $('#ppCashAmount').value = 0;
        if ($('#ppGPayCheck') && !$('#ppGPayCheck').checked && $('#ppGPayAmount')) $('#ppGPayAmount').value = 0;
        if ($('#ppPhonePeCheck') && !$('#ppPhonePeCheck').checked && $('#ppPhonePeAmount')) $('#ppPhonePeAmount').value = 0;
        if ($('#ppBankCheck') && !$('#ppBankCheck').checked && $('#ppBankAmount')) $('#ppBankAmount').value = 0;
        updatePpBalance();
    };"""
        new_func = """    window.togglePpInputs = async function() {
        if ($('#ppCashCheck') && $('#ppCashGroup')) $('#ppCashGroup').style.display = $('#ppCashCheck').checked ? 'block' : 'none';
        if ($('#ppGPayCheck') && $('#ppGPayGroup')) {
            $('#ppGPayGroup').style.display = $('#ppGPayCheck').checked ? 'block' : 'none';
            if ($('#ppGPayCheck').checked) {
                try { await window.loadGlobalUpiAccounts(); } catch(e){}
                if ($('#ppUpiAccount')) $('#ppUpiAccount').focus();
            }
        }
        if ($('#ppPhonePeCheck') && $('#ppPhonePeGroup')) $('#ppPhonePeGroup').style.display = $('#ppPhonePeCheck').checked ? 'block' : 'none';
        if ($('#ppBankCheck') && $('#ppBankGroup')) $('#ppBankGroup').style.display = $('#ppBankCheck').checked ? 'block' : 'none';
        if ($('#ppCashCheck') && !$('#ppCashCheck').checked && $('#ppCashAmount')) $('#ppCashAmount').value = 0;
        if ($('#ppGPayCheck') && !$('#ppGPayCheck').checked && $('#ppGPayAmount')) $('#ppGPayAmount').value = 0;
        if ($('#ppPhonePeCheck') && !$('#ppPhonePeCheck').checked && $('#ppPhonePeAmount')) $('#ppPhonePeAmount').value = 0;
        if ($('#ppBankCheck') && !$('#ppBankCheck').checked && $('#ppBankAmount')) $('#ppBankAmount').value = 0;
        updatePpBalance();
    };"""
        content = content.replace(old_func, new_func)

    with open(filename, 'w', encoding='utf-8') as f:
        f.write(content)

patch_script('static/js/script.js')

def patch_agency(filename):
    with open(filename, 'r', encoding='utf-8') as f:
        content = f.read()

    # Agency Purchase toggleAgPayment
    if 'window.toggleAgPayment = function () {' in content:
        content = content.replace('window.toggleAgPayment = function () {', 'window.toggleAgPayment = async function () {')
        old_ag = """if (val === 'UPI') {
            $('#agGpayInputGroup').style.display = 'block';
        } else {"""
        new_ag = """if (val === 'UPI') {
            $('#agGpayInputGroup').style.display = 'block';
            try { await window.loadGlobalUpiAccounts(); } catch(e){}
        } else {"""
        content = content.replace(old_ag, new_ag)

    # Supplier Refund toggleRmRefundMode
    if 'window.toggleRmRefundMode = function () {' in content:
        content = content.replace('window.toggleRmRefundMode = function () {', 'window.toggleRmRefundMode = async function () {')
        old_rm = """if (val === 'UPI') {
            $('#rmRefundUpiGroup').style.display = 'block';
        } else {"""
        new_rm = """if (val === 'UPI') {
            $('#rmRefundUpiGroup').style.display = 'block';
            try { await window.loadGlobalUpiAccounts(); } catch(e){}
        } else {"""
        content = content.replace(old_rm, new_rm)

    with open(filename, 'w', encoding='utf-8') as f:
        f.write(content)

patch_agency('static/js/agency.js')
print("Done")
