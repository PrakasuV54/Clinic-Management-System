import re

def fix_file(filename):
    with open(filename, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Update loadGlobalUpiAccounts's populateDropdown to use short_name || account_name
    old_pop = """window.globalUpiAccounts.map(a => `<option value="${a.id}">${a.account_name}</option>`).join('');"""
    new_pop = """window.globalUpiAccounts.map(a => `<option value="${a.short_name || a.account_name}">${a.account_name}</option>`).join('');"""
    content = content.replace(old_pop, new_pop)

    old_pop2 = """window.globalUpiAccounts.map(a => `<option value="${a.id}">${a.account_name} ${a.short_name ? `(${a.short_name})` : ''}</option>`).join('');"""
    new_pop2 = """window.globalUpiAccounts.map(a => `<option value="${a.short_name || a.account_name}">${a.account_name} ${a.short_name ? `(${a.short_name})` : ''}</option>`).join('');"""
    content = content.replace(old_pop2, new_pop2)

    # 2. Fix togglePayInputs
    # We will just replace the specific if (cGpay) block inside togglePayInputs
    
    pattern1 = re.compile(r'if \(\$\(\'#payUpiAccount\'\)\.options\.length <= 1\) \{\s*\$\(\'#payUpiAccount\'\)\.innerHTML = \'<option value="">Select Account</option>\' \+\s*\(window\.globalUpiAccounts \|\| \[\]\)\.map\(a => `<option value="\$\{a\.short_name \|\| a\.account_name\}">\$\{a\.account_name\} \$\{a\.short_name \? `\(\$\{a\.short_name\}\)` : \'\'\}</option>`\)\.join\(\'\'\);\s*\}')
    replacement1 = r"""await window.loadGlobalUpiAccounts();
            if ($('#payUpiAccount')) $('#payUpiAccount').focus();"""
    content = re.sub(pattern1, replacement1, content)
    
    # Wait, the best way to change togglePayInputs to async is:
    content = content.replace("window.togglePayInputs = function () {", "window.togglePayInputs = async function () {")
    content = content.replace("window.togglePpInputs = function () {", "window.togglePpInputs = async function () {")

    # 3. Fix togglePpInputs
    pattern2 = re.compile(r'if\(\$\(\'#ppUpiAccount\'\)\.options\.length <= 1\) \{\s*\$\(\'#ppUpiAccount\'\)\.innerHTML = \'<option value="">Select Account</option>\' \+\s*\(window\.globalUpiAccounts \|\| \[\]\)\.map\(a => `<option value="\$\{a\.short_name \|\| a\.account_name\}">\$\{a\.account_name\} \$\{a\.short_name \? `\(\$\{a\.short_name\}\)` : \'\'\}</option>`\)\.join\(\'\'\);\s*\}')
    replacement2 = r"""await window.loadGlobalUpiAccounts();
            if ($('#ppUpiAccount')) $('#ppUpiAccount').focus();"""
    content = re.sub(pattern2, replacement2, content)

    # Note: What if the id replacement wasn't done yet? (Because I restored from original)
    # The original file has `<option value="${a.id}">`
    pattern1_orig = re.compile(r'if\(\$\(\'#payUpiAccount\'\)\.options\.length <= 1\) \{\s*\$\(\'#payUpiAccount\'\)\.innerHTML = \'<option value="">Select Account</option>\' \+\s*\(window\.globalUpiAccounts \|\| \[\]\)\.map\(a => `<option value="\$\{a\.id\}">\$\{a\.account_name\} \$\{a\.short_name \? `\(\$\{a\.short_name\}\)` : \'\'\}</option>`\)\.join\(\'\'\);\s*\}')
    content = re.sub(pattern1_orig, replacement1, content)

    pattern2_orig = re.compile(r'if\(\$\(\'#ppUpiAccount\'\)\.options\.length <= 1\) \{\s*\$\(\'#ppUpiAccount\'\)\.innerHTML = \'<option value="">Select Account</option>\' \+\s*\(window\.globalUpiAccounts \|\| \[\]\)\.map\(a => `<option value="\$\{a\.id\}">\$\{a\.account_name\} \$\{a\.short_name \? `\(\$\{a\.short_name\}\)` : \'\'\}</option>`\)\.join\(\'\'\);\s*\}')
    content = re.sub(pattern2_orig, replacement2, content)

    with open(filename, 'w', encoding='utf-8') as f:
        f.write(content)

fix_file('static/js/script_final.js')
fix_file('static/js/agency_final.js')
print("Done")
