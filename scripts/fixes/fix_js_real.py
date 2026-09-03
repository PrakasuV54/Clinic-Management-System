import re

def fix_file(filename):
    with open(filename, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Update populateDropdown and other innerHTML options
    content = content.replace('`<option value="${a.id}">${a.account_name}</option>`', '`<option value="${a.short_name || a.account_name}">${a.account_name}</option>`')
    content = content.replace('`<option value="${a.id}">${a.account_name} ${a.short_name ? `(${a.short_name})` : \'\'}</option>`', '`<option value="${a.short_name || a.account_name}">${a.account_name} ${a.short_name ? `(${a.short_name})` : \'\'}</option>`')

    # 2. Fix togglePayInputs
    content = content.replace('window.togglePayInputs = function () {', 'window.togglePayInputs = async function () {')
    
    pattern_gpay = re.compile(r'if\(\$\(\'#payUpiAccount\'\)\.options\.length <= 1\) \{\s*\$\(\'#payUpiAccount\'\)\.innerHTML = \'<option value="">Select Account</option>\' \+\s*\(window\.globalUpiAccounts \|\| \[\]\)\.map\(a => `<option value="\$\{a\.short_name \|\| a\.account_name\}">\$\{a\.account_name\} \$\{a\.short_name \? `\(\$\{a\.short_name\}\)` : \'\'\}</option>`\)\.join\(\'\'\);\s*\}')
    new_gpay = """await window.loadGlobalUpiAccounts();
            if ($('#payUpiAccount')) $('#payUpiAccount').focus();"""
    content = re.sub(pattern_gpay, new_gpay, content)
    
    # 3. Fix togglePpInputs
    content = content.replace('window.togglePpInputs = function () {', 'window.togglePpInputs = async function () {')
    
    pattern_pp = re.compile(r'if\(\$\(\'#ppUpiAccount\'\)\.options\.length <= 1\) \{\s*\$\(\'#ppUpiAccount\'\)\.innerHTML = \'<option value="">Select Account</option>\' \+\s*\(window\.globalUpiAccounts \|\| \[\]\)\.map\(a => `<option value="\$\{a\.short_name \|\| a\.account_name\}">\$\{a\.account_name\} \$\{a\.short_name \? `\(\$\{a\.short_name\}\)` : \'\'\}</option>`\)\.join\(\'\'\);\s*\}')
    new_pp = """await window.loadGlobalUpiAccounts();
            if ($('#ppUpiAccount')) $('#ppUpiAccount').focus();"""
    content = re.sub(pattern_pp, new_pp, content)

    # Note: What if the target still has ${a.id}?
    pattern_gpay_orig = re.compile(r'if\(\$\(\'#payUpiAccount\'\)\.options\.length <= 1\) \{\s*\$\(\'#payUpiAccount\'\)\.innerHTML = \'<option value="">Select Account</option>\' \+\s*\(window\.globalUpiAccounts \|\| \[\]\)\.map\(a => `<option value="\$\{a\.id\}">\$\{a\.account_name\} \$\{a\.short_name \? `\(\$\{a\.short_name\}\)` : \'\'\}</option>`\)\.join\(\'\'\);\s*\}')
    content = re.sub(pattern_gpay_orig, new_gpay, content)

    pattern_pp_orig = re.compile(r'if\(\$\(\'#ppUpiAccount\'\)\.options\.length <= 1\) \{\s*\$\(\'#ppUpiAccount\'\)\.innerHTML = \'<option value="">Select Account</option>\' \+\s*\(window\.globalUpiAccounts \|\| \[\]\)\.map\(a => `<option value="\$\{a\.id\}">\$\{a\.account_name\} \$\{a\.short_name \? `\(\$\{a\.short_name\}\)` : \'\'\}</option>`\)\.join\(\'\'\);\s*\}')
    content = re.sub(pattern_pp_orig, new_pp, content)

    with open(filename, 'w', encoding='utf-8') as f:
        f.write(content)

fix_file('static/js/script.js')
fix_file('static/js/agency.js')
print("Done")

