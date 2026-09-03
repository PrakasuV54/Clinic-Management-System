import re

with open('templates/pharmacy.html', 'r', encoding='utf-8') as f:
    content = f.read()

# Restore payUpiAccount
pattern1 = re.compile(r'<div id="gpayInputGroup" style="display: none;" class="form-group">\s*<label[^>]*>UPI Amount[^<]*</label>\s*<input type="number" id="payGPayAmount"[^>]*>\s*</div>')
repl1 = '''<div id="gpayInputGroup" style="display: none;" class="form-group">
    <label style="font-size: 0.7rem;">UPI Amount (?)</label>
    <input type="number" id="payGPayAmount" value="0" oninput="updateGrandTotal()" onfocus="if(this.value==='0') this.value='';" onblur="if(this.value==='') this.value='0';">
    <label style="font-size: 0.8rem; margin-top: 12px; display: block; color: var(--primary); font-weight: 600;">Select UPI Account *</label>
    <select id="payUpiAccount" class="form-control" style="font-size: 1rem; padding: 10px; height: auto; border: 2px solid var(--primary); border-radius: 6px; background-color: #f8fafc; cursor: pointer;"></select>
</div>'''
content = re.sub(pattern1, repl1, content)

# Restore ppUpiAccount
pattern2 = re.compile(r'<div id="ppGPayGroup" style="display: none;" class="form-group">\s*<label[^>]*>UPI Amount[^<]*</label>\s*<input type="number" id="ppGPayAmount"[^>]*>\s*</div>')
repl2 = '''<div id="ppGPayGroup" style="display: none;" class="form-group">
    <label style="font-size: 0.7rem;">UPI Amount (?)</label>
    <input type="number" id="ppGPayAmount" value="0" oninput="updatePpBalance()" onfocus="if(this.value==='0') this.value='';" onblur="if(this.value==='') this.value='0';">
    <label style="font-size: 0.8rem; margin-top: 12px; display: block; color: var(--primary); font-weight: 600;">Select UPI Account *</label>
    <select id="ppUpiAccount" class="form-control" style="font-size: 1rem; padding: 10px; height: auto; border: 2px solid var(--primary); border-radius: 6px; background-color: #f8fafc; cursor: pointer;"></select>
</div>'''
content = re.sub(pattern2, repl2, content)

import time
t = int(time.time())
content = re.sub(r'js/script\.js\?v=\d+', f'js/script.js?v={t}', content)

with open('templates/pharmacy.html', 'w', encoding='utf-8') as f:
    f.write(content)

print("Pharmacy restored!")
