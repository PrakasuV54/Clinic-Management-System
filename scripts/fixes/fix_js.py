import re

with open('static/js/script_final.js', 'r', encoding='utf-8') as f:
    content = f.read()

# Fix togglePayInputs
pattern = re.compile(r'window\.togglePayInputs = async function \(\) \{.*?updateGrandTotal\(\);\s*\}', re.DOTALL)
replacement = '''window.togglePayInputs = async function () {
    const cCash = #payCashCheck.checked;
    const cGpay = #payGPayCheck.checked;

    if (cCash) #cashInputGroup.style.display = 'block';
    else { #cashInputGroup.style.display = 'none'; #payCashAmount.value = 0; }

    if (cGpay) {
        #gpayInputGroup.style.display = 'block';
        try { await window.loadGlobalUpiAccounts(); } catch(e) {}
        #payUpiAccount.innerHTML = '<option value="">Select Account</option>' + 
            (window.globalUpiAccounts || []).map(a => <option value=""></option>).join('');
        #payUpiAccount.focus();
    }
    else { #gpayInputGroup.style.display = 'none'; #payGPayAmount.value = 0; }

    updateGrandTotal();
}'''
content = re.sub(pattern, replacement, content)

with open('static/js/script_final.js', 'w', encoding='utf-8') as f:
    f.write(content)

with open('static/js/agency_final.js', 'r', encoding='utf-8') as f:
    agency_content = f.read()
agency_content = re.sub(pattern, replacement, agency_content)
with open('static/js/agency_final.js', 'w', encoding='utf-8') as f:
    f.write(agency_content)
