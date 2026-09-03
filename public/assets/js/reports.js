// ═══════════════════════════════════════════
// REPORT DASHBOARD LOGIC
// ═══════════════════════════════════════════

let reportData = null;

async function loadReportDashboard(period = null, force = false) {
    const reportSection = document.getElementById('sectionReportDashboard');
    // Proceed with fetching data even if hidden, so it's ready when viewed
    
    if (!period) {
        period = document.getElementById('reportPeriod').value;
    }

    let start = '';
    let end = '';
    if (period === 'custom') {
        start = document.getElementById('reportDateStart').value;
        end = document.getElementById('reportDateEnd').value;
        if (!start || !end) return; // Wait for both dates
    }

    try {
        const url = `/reports_api.php?action=get_reports&period=${period}&start=${start}&end=${end}&_=${new Date().getTime()}`;
        const res = await fetch(url).then(r => r.json());
        
        reportData = res;
        renderReportDashboard(res);
    } catch (e) {
        console.error("Error loading reports", e);
    }
}

function renderReportDashboard(data) {
    if (!data.executive) return;

    // Executive Summary
    document.getElementById('repTotalPatients').textContent = data.executive.total_patients;
    document.getElementById('repTotalConsultations').textContent = data.executive.total_consultations;
    document.getElementById('repTotalRevenue').textContent = '₹' + data.executive.total_revenue.toFixed(2);
    document.getElementById('repNetProfit').textContent = '₹' + data.executive.net_profit.toFixed(2);
    document.getElementById('repTotalExpenses').textContent = '₹' + data.executive.total_expenses.toFixed(2);

    // Secondary Summaries
    if(document.getElementById('repGentsFee')) document.getElementById('repGentsFee').textContent = '₹' + (data.executive.gents_doctor_revenue || 0).toFixed(2);
    if(document.getElementById('repLadiesFee')) document.getElementById('repLadiesFee').textContent = '₹' + (data.executive.ladies_doctor_revenue || 0).toFixed(2);
    if(document.getElementById('repClearedPending')) document.getElementById('repClearedPending').textContent = '₹' + (data.executive.cleared_pending || 0).toFixed(2);
    if(document.getElementById('repTotalReturns')) document.getElementById('repTotalReturns').textContent = '₹' + (data.executive.total_returns || 0).toFixed(2);
    
    // Doctor Report Table
    const docTbody = document.getElementById('repDoctorTable');
    if (docTbody) {
        docTbody.innerHTML = '';
        if (data.doctors && data.doctors.length > 0) {
            data.doctors.forEach(d => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${d.doctor_name}</td>
                    <td>${d.doctor_type}</td>
                    <td>${d.p_count}</td>
                    <td>₹${(d.doc_rev || 0).toFixed(2)}</td>
                    <td>₹${(d.med_rev || 0).toFixed(2)}</td>
                    <td>₹${((d.doc_rev || 0) + (d.med_rev || 0)).toFixed(2)}</td>
                `;
                docTbody.appendChild(tr);
            });
        } else {
            docTbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No data available</td></tr>';
        }
    }

    // Patient Report Table
    const patTbody = document.getElementById('repPatientTable');
    if (patTbody) {
        patTbody.innerHTML = '';
        if (data.patients && data.patients.length > 0) {
            data.patients.forEach(p => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${p.name}</td>
                    <td>${p.phone}</td>
                    <td>${p.doctor_name}</td>
                    <td>${p.created_at.split(' ')[0]}</td>
                    <td>₹${(p.consultation_fee || 0).toFixed(2)}</td>
                    <td>₹${(p.total_amount || 0).toFixed(2)}</td>
                    <td>
                        <span class="status-badge status-${p.balance_amount > 0 ? 'pending' : 'completed'}">
                            ${p.balance_amount > 0 ? 'Pending (₹' + p.balance_amount + ')' : 'Paid'}
                        </span>
                    </td>
                `;
                patTbody.appendChild(tr);
            });
        } else {
            patTbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No patients found</td></tr>';
        }
    }
    
    // Top Medicines
    const medTbody = document.getElementById('repMedicineTable');
    if (medTbody) {
        medTbody.innerHTML = '';
        if (data.top_medicines && data.top_medicines.length > 0) {
            data.top_medicines.forEach(m => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${m.name}</td>
                    <td>${m.qty}</td>
                    <td>₹${(m.revenue || 0).toFixed(2)}</td>
                `;
                medTbody.appendChild(tr);
            });
        } else {
            medTbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">No medicine sales</td></tr>';
        }
    }

    // Returns Report Table
    const retTbody = document.getElementById('repReturnsTable');
    if (retTbody) {
        retTbody.innerHTML = '';
        if (data.returns && data.returns.length > 0) {
            data.returns.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${r.return_date} ${r.return_time}</td>
                    <td>${r.patient_name || '-'}</td>
                    <td>${r.bill_number || '-'}</td>
                    <td>${r.medicine_name}</td>
                    <td>${r.returned_qty}</td>
                    <td style="color:var(--danger); font-weight:600;">₹${parseFloat(r.return_amount || 0).toFixed(2)}</td>
                    <td>${r.refund_payment_mode || 'Cash'}</td>
                    <td>${r.processed_by || '-'}</td>
                `;
                retTbody.appendChild(tr);
            });
        } else {
            retTbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No returns found</td></tr>';
        }
    }

    // Direct Sales Report Table
    const dsTbody = document.getElementById('repDirectSalesTable');
    if (dsTbody) {
        dsTbody.innerHTML = '';
        if (data.direct_sales && data.direct_sales.length > 0) {
            data.direct_sales.forEach(s => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${s.customer_name}</td>
                    <td>${s.mobile_number}</td>
                    <td style="font-weight:bold;">₹${parseFloat(s.total_amount || 0).toFixed(2)}</td>
                    <td>₹${parseFloat(s.paid_amount || 0).toFixed(2)}</td>
                    <td>
                        <span class="status-badge status-${s.balance_amount > 0 ? 'pending' : 'completed'}">
                            ${s.balance_amount > 0 ? 'Pending (₹' + s.balance_amount + ')' : 'Paid'}
                        </span>
                    </td>
                    <td>${(s.created_at || '').split(' ')[0]}</td>
                `;
                dsTbody.appendChild(tr);
            });
        } else {
            dsTbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No direct sales found</td></tr>';
        }
    }
}

function switchReportTab(tabId) {
    document.querySelectorAll('.report-tab-btn').forEach(b => {
        b.classList.remove('active');
        b.style.borderBottom = 'none';
        b.style.color = 'var(--text-muted)';
        if (b.innerText.trim() === 'Returns') b.style.color = 'var(--danger)'; // Default for Returns
    });
    document.querySelectorAll('.report-tab-content').forEach(c => c.style.display = 'none');
    
    const activeBtn = document.querySelector(`[onclick="switchReportTab('${tabId}')"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.style.borderBottom = '3px solid var(--primary)';
        activeBtn.style.color = 'var(--primary)';
        if (tabId === 'rtReturns') {
            activeBtn.style.borderBottom = '3px solid var(--danger)';
            activeBtn.style.color = 'var(--danger)';
        }
    }
    document.getElementById(tabId).style.display = 'block';
}

async function exportReport(format) {
    const period = document.getElementById('reportPeriod').value;
    let start = document.getElementById('reportDateStart').value;
    let end = document.getElementById('reportDateEnd').value;
    
    try {
        const btn = format === 'csv' ? document.querySelector(`[onclick="exportReport('csv')"]`) : document.querySelector(`[onclick="exportReport('print')"]`);
        const oldText = btn ? btn.innerHTML : 'Export';
        if (btn) { btn.innerHTML = 'Generating...'; btn.disabled = true; }

        const url = `/reports_api.php?action=get_print_report&period=${period}&start=${start}&end=${end}&_=${new Date().getTime()}`;
        const data = await fetch(url).then(r => r.json());
        
        if (format === 'csv' || format === 'excel') {
            generateExcelReport(data, period, start, end);
        } else if (format === 'print' || format === 'pdf' || format === 'whatsapp') {
            generatePrintableReport(data, period, start, end, format);
        }
        
        if (btn) { btn.innerHTML = oldText; btn.disabled = false; }
    } catch (e) {
        console.error("Error generating report", e);
        alert("Failed to generate report.");
    }
}

function generateExcelReport(data, period, start, end) {
    if (typeof XLSX === 'undefined') {
        alert("Excel export library is loading. Please try again in a few seconds.");
        return;
    }

    let periodText = period.charAt(0).toUpperCase() + period.slice(1);
    if (period === 'custom') periodText = `${start} to ${end}`;

    const wb = XLSX.utils.book_new();

    // 1. Executive Summary
    const execData = [
        ["Metric", "Value"],
        ["Total Revenue", data.executive.total_revenue || 0],
        ["Total Expenses", data.executive.total_expenses || 0],
        ["Net Profit", data.executive.net_profit || 0],
        ["Gents Doctor Revenue", data.executive.gents_doctor_revenue || 0],
        ["Ladies Doctor Revenue", data.executive.ladies_doctor_revenue || 0],
        ["Pending Collections", data.executive.pending_amount || 0],
        ["Cleared Pending", data.executive.cleared_pending || 0],
        ["Total Patients", data.executive.total_patients || 0],
        ["Total Returns", data.executive.total_returns || 0],
    ];
    if (data.financial) {
        Object.keys(data.financial).forEach(key => {
            execData.push([key + " Collected", data.financial[key] || 0]);
        });
    }
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(execData), "Executive Summary");

    // 2. Patient History
    const patientData = [["Patient Name", "Phone", "Doctor", "Visit Date", "Consultation Fee", "Medicines Purchased", "Medicine Bill", "Injection Cost", "IV Cost", "Total Amount", "Paid", "Pending", "Payment Mode"]];
    if (data.patients) {
        data.patients.forEach(p => {
            patientData.push([
                p.name, p.phone, p.doctor_name, (p.created_at || '').split(' ')[0], 
                p.consultation_fee || 0, p.parsed_medicines || '', p.cost_amount || 0, 
                p.injection_cost || 0, p.iv_cost || 0, p.total_amount || 0, 
                p.paid_amount || 0, p.balance_amount || 0, 
                (p.cash_amount > 0 ? 'Cash ' : '') + (p.gpay_amount > 0 ? (p.upi_account ? p.upi_account + ' ' : 'UPI ') : '') + (p.bank_amount > 0 ? 'Bank ' : '')
            ]);
        });
    }
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(patientData), "Patients");

    // 3. Direct Sales
    const salesData = [["Customer", "Phone", "Date", "Medicines", "Total Amount", "Paid Amount", "Pending Amount"]];
    if (data.direct_sales) {
        data.direct_sales.forEach(s => {
            salesData.push([s.customer_name, s.mobile_number, (s.created_at || '').split(' ')[0], s.medicines, s.total_amount || 0, s.paid_amount || 0, s.balance_amount || 0]);
        });
    }
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(salesData), "Direct Sales");

    // 4. Returns
    const retData = [["Patient / Customer", "Return Date", "Medicine", "Qty", "Refund Amount"]];
    if (data.returns) data.returns.forEach(r => retData.push([r.patient_name || 'Direct Sale', r.return_date || '', r.medicine_name || '', r.returned_qty || 0, r.return_amount || 0]));
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(retData), "Returns");

    // 5. Pending
    const pendingData = [["Patient / Customer", "Phone", "Date", "Pending Amount"]];
    if (data.patients) data.patients.filter(p => p.balance_amount > 0).forEach(p => pendingData.push([p.name, p.phone, p.created_at || '', p.balance_amount || 0]));
    if (data.direct_sales) data.direct_sales.filter(p => p.balance_amount > 0).forEach(p => pendingData.push([p.customer_name, p.mobile_number, p.created_at || '', p.balance_amount || 0]));
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(pendingData), "Pending");

    // 6. Inventory
    const invData = [["Medicine Name", "Current Stock", "MRP"]];
    if (data.inventory) data.inventory.forEach(i => invData.push([i.name, i.stock, i.mrp || 0]));
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(invData), "Inventory");

    // 7. Agency Purchases
    const agencyData = [["Supplier", "Invoice", "Purchase Date", "Grand Total", "Payment Mode"]];
    if (data.agency_purchases) data.agency_purchases.forEach(a => agencyData.push([a.supplier_name, a.invoice_number, a.purchase_date, a.grand_total || 0, a.payment_mode]));
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(agencyData), "Supplier Purchases");

    XLSX.writeFile(wb, `Hospital_Complete_Report_${new Date().getTime()}.xlsx`);
}

function generatePrintableReport(data, period, start, end, format = 'print') {
    const dateStr = new Date().toLocaleString();
    let periodText = period.charAt(0).toUpperCase() + period.slice(1);
    if (period === 'custom') periodText = `Custom (${start} to ${end})`;

    let titleText = 'Comprehensive Master Report';
    if (format === 'pdf' && period === 'custom' && start === '2000-01-01') {
        titleText = 'System Full Backup';
    }

    const groupByDate = (arr, dateField) => {
        if (!arr || !arr.length) return [];
        const grouped = {};
        arr.forEach(item => {
            const d = (item[dateField] || '').split(' ')[0] || 'Unknown Date';
            if (!grouped[d]) grouped[d] = [];
            grouped[d].push(item);
        });
        return Object.keys(grouped).sort((a,b) => b.localeCompare(a)).map(date => ({ date, items: grouped[date] }));
    };

    const groupedPatients = groupByDate(data.patients, 'created_at');
    const groupedDirectSales = groupByDate(data.direct_sales, 'created_at');
    const groupedPurchases = groupByDate(data.agency_purchases, 'purchase_date');
    const groupedReturns = groupByDate(data.returns, 'return_date');

    let html = `
    <!DOCTYPE html>
    <html>
    <head>
        <title>Hospital System Report</title>
        <style>
            @media print {
                @page { margin: 15mm; size: auto; }
                body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .no-break { page-break-inside: avoid; }
            }
            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.5; color: #222; margin: 0; padding: 20px; background: #fff; }
            .header { text-align: center; border-bottom: 2px solid #222; padding-bottom: 10px; margin-bottom: 20px; }
            .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
            .header h2 { margin: 5px 0; font-size: 18px; color: #444; }
            .meta-info { display: flex; justify-content: space-between; font-size: 12px; color: #555; margin-bottom: 20px; }
            .section { margin-bottom: 30px; }
            .section-title { font-size: 16px; background: #f0f0f0; padding: 8px 12px; border-left: 4px solid #2563eb; margin-bottom: 15px; font-weight: 600; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 12px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f8fafc; font-weight: 600; color: #333; }
            .grid-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
            .stat-box { border: 1px solid #e2e8f0; padding: 15px; border-radius: 4px; text-align: center; }
            .stat-box .title { font-size: 11px; color: #64748b; text-transform: uppercase; margin-bottom: 5px; }
            .stat-box .val { font-size: 18px; font-weight: bold; color: #0f172a; }
            .footer { margin-top: 50px; padding-top: 10px; border-top: 1px solid #ddd; text-align: center; font-size: 11px; color: #777; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
        </style>
    </head>
    <body>
        <div class="print-wrapper" style="background:#fff; padding:30px;">
            <div class="header">
                <h1 style="margin: 0; font-size: 26px; text-transform: uppercase; letter-spacing: 1.5px;">Crescent Clinic & Scans</h1>
                <h2 style="margin: 5px 0; font-size: 18px; color: #444;">${titleText}</h2>
            </div>
        
        <div class="meta-info">
            <div><strong>Date Filter:</strong> ${periodText}</div>
            <div><strong>Generated At:</strong> ${dateStr}</div>
            <div><strong>Generated By:</strong> Administrator</div>
        </div>

        <div class="section no-break">
            <div class="section-title">1. EXECUTIVE SUMMARY</div>
            <div class="grid-stats">
                <div class="stat-box">
                    <div class="title">Total Revenue</div>
                    <div class="val">₹${(data.executive.total_revenue || 0).toFixed(2)}</div>
                </div>
                <div class="stat-box">
                    <div class="title">Total Expenses</div>
                    <div class="val">₹${(data.executive.total_expenses || 0).toFixed(2)}</div>
                </div>
                <div class="stat-box">
                    <div class="title">Net Profit</div>
                    <div class="val">₹${(data.executive.net_profit || 0).toFixed(2)}</div>
                </div>
                <div class="stat-box">
                    <div class="title">Gents Doctor Fee</div>
                    <div class="val">₹${(data.executive.gents_doctor_revenue || 0).toFixed(2)}</div>
                </div>
            </div>
            <div class="grid-stats">
                <div class="stat-box">
                    <div class="title">Ladies Doctor Fee</div>
                    <div class="val">₹${(data.executive.ladies_doctor_revenue || 0).toFixed(2)}</div>
                </div>
                <div class="stat-box">
                    <div class="title">Pending Collections</div>
                    <div class="val">₹${(data.executive.pending_amount || 0).toFixed(2)}</div>
                </div>
                <div class="stat-box">
                    <div class="title">Cleared Pending</div>
                    <div class="val">₹${(data.executive.cleared_pending || 0).toFixed(2)}</div>
                </div>
                <div class="stat-box">
                    <div class="title">Total Patients</div>
                    <div class="val">${data.executive.total_patients || 0}</div>
                </div>
            </div>
            <div class="grid-stats">
                <div class="stat-box">
                    <div class="title">Direct Med Sales</div>
                    <div class="val">${data.executive.direct_sales_count || 0}</div>
                </div>
                <div class="stat-box">
                    <div class="title">Total Returns</div>
                    <div class="val">₹${(data.executive.total_returns || 0).toFixed(2)}</div>
                </div>
                <div class="stat-box">
                    <div class="title">Consultations</div>
                    <div class="val">${data.executive.total_consultations || 0}</div>
                </div>
                <div class="stat-box">
                    <div class="title">Total Suppliers</div>
                    <div class="val">${data.executive.total_suppliers || 0}</div>
                </div>
            </div>
        </div>

        <!-- 2. FINANCIAL & PAYMENT MODE ANALYSIS -->
        <div class="section no-break">
            <div class="section-title">2. FINANCIAL & PAYMENT MODE ANALYSIS</div>
            <table>
                <tr>
                    ${Object.keys(data.financial || {}).map(k => `<th>${k}</th>`).join('')}
                    <th>Total Collected</th>
                </tr>
                <tr>
                    ${Object.values(data.financial || {}).map(v => `<td>₹${parseFloat(v || 0).toFixed(2)}</td>`).join('')}
                    <td><strong>₹${(data.executive.collection_amount || 0).toFixed(2)}</strong></td>
                </tr>
            </table>
        </div>

        <!-- 3. DOCTOR REVENUE SUMMARY -->
        <div class="section no-break">
            <div class="section-title">3. DOCTOR REVENUE SUMMARY</div>
            <table>
                <thead>
                    <tr>
                        <th>Doctor Name</th>
                        <th>Type</th>
                        <th class="text-center">Consultations</th>
                        <th class="text-right">Consultation Rev</th>
                        <th class="text-right">Medicine Rev</th>
                        <th class="text-right">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.doctors && data.doctors.length > 0 ? data.doctors.map(d => `
                        <tr>
                            <td>${d.doctor_name}</td>
                            <td>${d.doctor_type}</td>
                            <td class="text-center">${d.p_count}</td>
                            <td class="text-right">₹${(d.doc_rev || 0).toFixed(2)}</td>
                            <td class="text-right">₹${(d.med_rev || 0).toFixed(2)}</td>
                            <td class="text-right"><strong>₹${((d.doc_rev || 0) + (d.med_rev || 0)).toFixed(2)}</strong></td>
                        </tr>
                    `).join('') : '<tr><td colspan="6" class="text-center">No data found</td></tr>'}
                </tbody>
            </table>
        </div>

        <!-- 4. PATIENT VISIT HISTORY -->
        <div class="section">
            <div class="section-title">4. PATIENT VISIT HISTORY</div>
            ${groupedPatients.length > 0 ? groupedPatients.map(g => `
                <div style="background:#f8fafc; padding:5px 10px; font-weight:bold; border:1px solid #cbd5e1; border-bottom:none; margin-top:10px;">Date: ${g.date}</div>
                <table style="margin-top:0;">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Phone</th>
                            <th>Doctor</th>
                            <th class="text-right">Fee</th>
                            <th>Medicines Purchased</th>
                            <th class="text-right">Total Amount</th>
                            <th class="text-right">Paid</th>
                            <th class="text-right">Balance</th>
                            <th class="text-center">Pay Mode</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${g.items.map(p => `
                            <tr class="no-break">
                                <td>${p.name}</td>
                                <td>${p.phone}</td>
                                <td>${p.doctor_name}</td>
                                <td class="text-right">₹${(p.consultation_fee || 0).toFixed(2)}</td>
                                <td><div style="max-width:150px;white-space:normal;overflow-wrap:break-word">${p.parsed_medicines || ''}</div></td>
                                <td class="text-right"><strong>₹${(p.total_amount || 0).toFixed(2)}</strong></td>
                                <td class="text-right">₹${(p.paid_amount || 0).toFixed(2)}</td>
                                <td class="text-right" style="color:${p.balance_amount>0?'red':'inherit'}">₹${(p.balance_amount || 0).toFixed(2)}</td>
                                <td class="text-center">${(p.cash_amount>0?'Cash ':'')}${(p.gpay_amount>0?(p.upi_account ? \`UPI (\${p.upi_account}) \` : 'UPI '):'')}${(p.bank_amount>0?'Bank':'')}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `).join('') : '<p class="text-center" style="padding:10px; border:1px solid #e2e8f0;">No patients found</p>'}
        </div>

        <!-- 5. PHARMACY - TOP SELLING MEDICINES -->
        <div class="section no-break">
            <div class="section-title">5. PHARMACY - TOP SELLING MEDICINES</div>
            <table>
                <thead>
                    <tr>
                        <th>Medicine Name</th>
                        <th class="text-center">Qty Sold</th>
                        <th class="text-right">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.top_medicines && data.top_medicines.length > 0 ? data.top_medicines.map(m => `
                        <tr>
                            <td>${m.name}</td>
                            <td class="text-center">${m.qty}</td>
                            <td class="text-right">₹${(m.revenue || 0).toFixed(2)}</td>
                        </tr>
                    `).join('') : '<tr><td colspan="3" class="text-center">No medicine sales found</td></tr>'}
                </tbody>
            </table>
        </div>

        <!-- 6. DIRECT MEDICINE SALES -->
        <div class="section">
            <div class="section-title">6. DIRECT MEDICINE SALES</div>
            ${groupedDirectSales.length > 0 ? groupedDirectSales.map(g => `
                <div style="background:#f8fafc; padding:5px 10px; font-weight:bold; border:1px solid #cbd5e1; border-bottom:none; margin-top:10px;">Date: ${g.date}</div>
                <table style="margin-top:0;">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Medicines</th>
                            <th class="text-right">Total Amount</th>
                            <th class="text-right">Paid Amount</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${g.items.map(s => `
                            <tr class="no-break">
                                <td>${s.customer_name}</td>
                                <td>${s.mobile_number}</td>
                                <td><div style="max-width:150px;white-space:normal;overflow-wrap:break-word">${s.medicines || ''}</div></td>
                                <td class="text-right">₹${(s.total_amount || 0).toFixed(2)}</td>
                                <td class="text-right">₹${(s.paid_amount || 0).toFixed(2)}</td>
                                <td class="text-center" style="color:${s.balance_amount>0?'red':'green'}">${s.balance_amount > 0 ? 'Pending' : 'Paid'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `).join('') : '<p class="text-center" style="padding:10px; border:1px solid #e2e8f0;">No direct sales found</p>'}
        </div>

        <!-- 7. SUPPLIER / AGENCY PURCHASES -->
        <div class="section">
            <div class="section-title">7. SUPPLIER / AGENCY PURCHASES</div>
            ${groupedPurchases.length > 0 ? groupedPurchases.map(g => `
                <div style="background:#f8fafc; padding:5px 10px; font-weight:bold; border:1px solid #cbd5e1; border-bottom:none; margin-top:10px;">Date: ${g.date}</div>
                <table style="margin-top:0;">
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Supplier Name</th>
                            <th>Payment Mode</th>
                            <th class="text-right">Grand Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${g.items.map(a => `
                            <tr class="no-break">
                                <td>${a.invoice_number}</td>
                                <td>${a.supplier_name}</td>
                                <td>${a.payment_mode}</td>
                                <td class="text-right"><strong>₹${(a.grand_total || 0).toFixed(2)}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `).join('') : '<p class="text-center" style="padding:10px; border:1px solid #e2e8f0;">No purchases found</p>'}
        </div>

        <!-- 8. CURRENT INVENTORY STATUS -->
        <div class="section">
            <div class="section-title">8. CURRENT INVENTORY STATUS</div>
            <table>
                <thead>
                    <tr>
                        <th>Medicine Name</th>
                        <th class="text-center">Current Stock</th>
                        <th class="text-right">MRP</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.inventory && data.inventory.length > 0 ? data.inventory.map(i => `
                        <tr class="no-break">
                            <td>${i.name}</td>
                            <td class="text-center">${i.stock}</td>
                            <td class="text-right">₹${(i.mrp || 0).toFixed(2)}</td>
                        </tr>
                    `).join('') : '<tr><td colspan="3" class="text-center">No inventory found</td></tr>'}
                </tbody>
            </table>
        </div>

        <div class="section no-break">
            <div class="section-title">9. MEDICINE RETURNS</div>
            ${(groupedReturns = (data.returns || []).reduce((acc, curr) => {
                const d = curr.return_date || 'N/A';
                if (!acc[d]) acc[d] = [];
                acc[d].push(curr);
                return acc;
            }, {}), Object.keys(groupedReturns).sort((a,b) => new Date(b) - new Date(a)).map(date => `
                <div style="background:#f8fafc; padding:5px 10px; font-weight:bold; border:1px solid #cbd5e1; border-bottom:none; margin-top:10px;">Date: ${date}</div>
                <table style="margin-top:0;">
                    <thead>
                        <tr><th>Patient / Type</th><th>Medicine</th><th>Qty</th><th class="text-right">Refund</th></tr>
                    </thead>
                    <tbody>
                        ${groupedReturns[date].map(r => `
                            <tr class="no-break">
                                <td>${r.patient_name || 'Direct Sale Return'}</td>
                                <td>${r.medicine_name}</td>
                                <td>${r.returned_qty}</td>
                                <td class="text-right" style="color:red">-₹${(r.return_amount || 0).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `).join('')) || '<p class="text-center" style="padding:10px; border:1px solid #e2e8f0;">No returns recorded</p>'}
        </div>
        
        ${(data.patients && data.patients.filter(p => p.balance_amount > 0).length > 0) || (data.direct_sales && data.direct_sales.filter(s => s.balance_amount > 0).length > 0) ? `
        <div class="section">
            <div class="section-title">Pending Payments</div>
            <table>
                <thead>
                    <tr><th>Name</th><th>Phone</th><th>Date</th><th class="text-right">Balance Due</th></tr>
                </thead>
                <tbody>
                    ${(data.patients || []).filter(p => p.balance_amount > 0).map(p => `<tr><td>${p.name}</td><td>${p.phone}</td><td>${(p.created_at || '').split(' ')[0]}</td><td class="text-right" style="color:red; font-weight:bold;">₹${(p.balance_amount||0).toFixed(2)}</td></tr>`).join('')}
                    ${(data.direct_sales || []).filter(s => s.balance_amount > 0).map(s => `<tr><td>${s.customer_name} (Direct)</td><td>${s.mobile_number}</td><td>${(s.created_at || '').split(' ')[0]}</td><td class="text-right" style="color:red; font-weight:bold;">₹${(s.balance_amount||0).toFixed(2)}</td></tr>`).join('')}
                </tbody>
            </table>
        </div>` : ''}

        <div class="footer">Generated by Crescent Clinic Management System &copy; ${new Date().getFullYear()}</div>
        </div>
    </body>
    </html>`;

    if (format === 'print') {
        const iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);
        iframe.contentWindow.document.open();
        iframe.contentWindow.document.write(html + '<script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };</script></body></html>');
        iframe.contentWindow.document.close();
        setTimeout(() => { document.body.removeChild(iframe); }, 10000);
        
        Swal.fire({
            icon: 'success',
            title: 'Print Ready',
            text: 'Opening print dialog...',
            timer: 1500,
            showConfirmButton: false
        });
        
        const btn = document.querySelector(`[onclick="exportReport('print')"]`);
        if (btn) { btn.innerHTML = 'Print & PDF'; btn.disabled = false; }
    } else {
        html += `</body></html>`;
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        // Make it renderable but hidden offscreen
        tempDiv.style.position = 'absolute';
        tempDiv.style.top = '-9999px';
        tempDiv.style.left = '0';
        tempDiv.style.width = '1200px';
        tempDiv.style.backgroundColor = '#fff';
        document.body.appendChild(tempDiv);
        
        // Let fonts/styles load
        setTimeout(async () => {
            const wrapper = tempDiv.querySelector('.print-wrapper');
            if (format === 'whatsapp') {
                if (typeof html2pdf === 'undefined') {
                    alert("html2pdf is loading, please try again.");
                    tempDiv.remove();
                    return;
                }
                Swal.fire({ title: 'Generating PDF...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const opt = {
                    margin: 0.2,
                    filename: `Hospital_Report_${new Date().getTime()}.pdf`,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
                };
                html2pdf().set(opt).from(wrapper).save().then(() => {
                    Swal.close();
                    
                    const waNum = localStorage.getItem('whatsapp_backup_number') || '';
                    let text = "*🏥 HOSPITAL MASTER REPORT*\n";
                    text += "Date Filter: " + periodText + "\n";
                    text += "Generated: " + dateStr + "\n\n";
                    text += "📊 *EXECUTIVE SUMMARY*\n";
                    text += "Total Revenue: ₹" + (data.executive.total_revenue || 0).toFixed(2) + "\n";
                    text += "Net Profit: ₹" + (data.executive.net_profit || 0).toFixed(2) + "\n";
                    text += "Total Patients: " + (data.executive.total_patients || 0) + "\n";
                    text += "Pending Collections: ₹" + (data.executive.pending_amount || 0).toFixed(2) + "\n";
                    text += "Cleared Pending: ₹" + (data.executive.cleared_pending || 0).toFixed(2) + "\n\n";
                    text += "💰 *FINANCIALS*\n";
                    if (data.financial) {
                        Object.keys(data.financial).forEach(k => {
                            text += k + ": ₹" + parseFloat(data.financial[k] || 0).toFixed(2) + "\n";
                        });
                    }
                    text += "\n";
                    text += "👨‍⚕️ *DOCTOR FEE*\n";
                    text += "Gents: ₹" + (data.executive.gents_doctor_revenue || 0).toFixed(2) + "\n";
                    text += "Ladies: ₹" + (data.executive.ladies_doctor_revenue || 0).toFixed(2) + "\n\n";
                    text += "_Please find the attached PDF report for full details._";
                    
                    window.open("https://wa.me/" + waNum + "?text=" + encodeURIComponent(text), '_blank');
                    tempDiv.remove();
                });
                
            } else if (format === 'pdf') {
                if (typeof html2pdf === 'undefined') {
                    alert("html2pdf is loading, please try again.");
                    tempDiv.remove();
                    return;
                }
                Swal.fire({ title: 'Generating PDF...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const opt = {
                    margin: 0.2,
                    filename: `Hospital_Backup_${new Date().getTime()}.pdf`,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
                };
                html2pdf().set(opt).from(wrapper).save().then(() => {
                    Swal.close();
                    tempDiv.remove();
                });
            }
        }, 800);
    }
}

function generateBackup() {
    exportReportCustom('custom', '2000-01-01', new Date().toISOString().split('T')[0], 'pdf');
}

async function exportReportCustom(period, start, end, format) {
    try {
        Swal.fire({ title: 'Preparing Full Backup...', text: 'Fetching all records, please wait.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const url = `/reports_api.php?action=get_print_report&period=${period}&start=${start}&end=${end}&_=${new Date().getTime()}`;
        const data = await fetch(url).then(r => r.json());
        Swal.close();
        generatePrintableReport(data, period, start, end, format);
    } catch (e) {
        console.error("Error generating backup", e);
        Swal.fire('Error', 'Failed to generate full backup.', 'error');
    }
}

function toggleReportCustomDate() {
    const period = document.getElementById('reportPeriod').value;
    const customGroup = document.getElementById('reportCustomDateGroup');
    if (period === 'custom') {
        customGroup.style.display = 'flex';
    } else {
        customGroup.style.display = 'none';
    }
}

function searchReport() {
    const query = document.getElementById('reportSearchInput').value.toLowerCase();
    
    // Filter Patients Table
    const patRows = document.querySelectorAll('#repPatientTable tr');
    patRows.forEach(row => {
        if (row.innerText.toLowerCase().includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    // Add filter logic for other tables as well
}

// Automatically bind to live sync
if (typeof reloadCurrentSection === 'function') {
    const originalReload = window.reloadCurrentSection;
    window.reloadCurrentSection = function() {
        originalReload();
        loadReportDashboard();
    };
} else {
    window.reloadCurrentSection = function() {
        loadReportDashboard();
    };
}

async function sendWhatsAppBackup() {
    let waNumber = localStorage.getItem('whatsapp_backup_number');
    
    if (!waNumber) {
        if (typeof Swal !== 'undefined') {
            const { value: number } = await Swal.fire({
                title: 'WhatsApp Backup Configuration',
                input: 'text',
                inputLabel: 'Enter WhatsApp Number (with country code, e.g., 919876543210)',
                inputPlaceholder: '91xxxxxxxxxx',
                showCancelButton: true,
                inputValidator: (value) => {
                    if (!value) return 'You need to enter a number!';
                    if (!/^\d{10,15}$/.test(value)) return 'Please enter a valid number with country code.';
                }
            });
            if (number) {
                waNumber = number;
                localStorage.setItem('whatsapp_backup_number', waNumber);
                Swal.fire('Saved!', 'WhatsApp number has been saved.', 'success');
            } else {
                return; // Cancelled
            }
        } else {
            waNumber = prompt("Enter WhatsApp Number (with country code, e.g., 919876543210):");
            if (waNumber && /^\d{10,15}$/.test(waNumber)) {
                localStorage.setItem('whatsapp_backup_number', waNumber);
            } else {
                alert("Invalid or missing number.");
                return;
            }
        }
    } else {
        // Offer to change number
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Send Backup to WhatsApp',
                text: `Current Backup Number: +${waNumber}`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Send Now',
                cancelButtonText: 'Cancel',
                showDenyButton: true,
                denyButtonText: 'Change Number',
            });
            
            if (result.isDenied) {
                localStorage.removeItem('whatsapp_backup_number');
                sendWhatsAppBackup(); // Recursively call to ask again
                return;
            } else if (!result.isConfirmed) {
                return; // Cancelled
            }
        } else {
            if (!confirm(`Send backup to +${waNumber}?\nClick Cancel to stop. (Clear localStorage to change number if no SweetAlert)`)) {
                return;
            }
        }
    }

    // Now trigger the export PDF & Text message
    // Now trigger the export PDF & Text message
    exportReport('whatsapp');
}

// ═══════════════════════════════════════════
// DETAILED REPORT MODAL LOGIC
// ═══════════════════════════════════════════
function showReportDetails(type) {
    if (!reportData || !reportData.executive) return;
    
    let title = 'Report Details';
    let html = '';
    
    if (type === 'revenue') {
        title = 'Total Revenue Breakdown';
        html = `
            <table class="data-table">
                <tr><th>Category</th><th style="text-align:right">Amount</th></tr>
                <tr><td>Doctor Consultations</td><td style="text-align:right">₹${(reportData.executive.gents_doctor_revenue + reportData.executive.ladies_doctor_revenue).toFixed(2)}</td></tr>
                <tr><td>Pharmacy Sales (from Prescriptions)</td><td style="text-align:right">₹${(reportData.executive.medicine_revenue || 0).toFixed(2)}</td></tr>
                <tr><td>Direct Medical Sales</td><td style="text-align:right">₹${(reportData.executive.direct_sales_revenue || 0).toFixed(2)}</td></tr>
                <tr><td>Injections / IVs</td><td style="text-align:right">₹${((reportData.executive.injection_revenue || 0) + (reportData.executive.iv_revenue || 0)).toFixed(2)}</td></tr>
                <tr style="background:#f0f9ff; font-weight:bold;"><td>Total Revenue</td><td style="text-align:right">₹${reportData.executive.total_revenue.toFixed(2)}</td></tr>
            </table>
            
            <h4 style="margin-top:20px;">Payment Modes</h4>
            <table class="data-table">
                <tr><th>Mode</th><th style="text-align:right">Collected</th></tr>
                ${Object.keys(reportData.financial || {}).map(k => `<tr><td>${k}</td><td style="text-align:right">₹${parseFloat(reportData.financial[k] || 0).toFixed(2)}</td></tr>`).join('')}
            </table>
        `;
    } else if (type === 'expenses') {
        title = 'Total Expenses Breakdown';
        html = `
            <table class="data-table">
                <tr><th>Category</th><th style="text-align:right">Amount</th></tr>
                <tr><td>Agency / Supplier Purchases</td><td style="text-align:right">₹${(reportData.executive.total_expenses || 0).toFixed(2)}</td></tr>
                <tr><td>Returns / Refunds</td><td style="text-align:right">₹${(reportData.executive.total_returns || 0).toFixed(2)}</td></tr>
                <tr style="background:#fef2f2; font-weight:bold;"><td>Total Outflow</td><td style="text-align:right">₹${((reportData.executive.total_expenses || 0) + (reportData.executive.total_returns || 0)).toFixed(2)}</td></tr>
            </table>
            <p style="margin-top:10px; color:var(--text-muted); font-size:0.85em;">* Detailed expense splitting requires Other Expenses module.</p>
        `;
    } else if (type === 'profit') {
        title = 'Net Profit Calculation';
        html = `
            <div style="padding:15px; background:#f8fafc; border-radius:8px; margin-bottom:15px;">
                <p><strong>Net Profit</strong> is calculated as the total revenue minus total expenses and total refund amounts.</p>
            </div>
            <table class="data-table">
                <tr><th>Component</th><th style="text-align:right">Value</th></tr>
                <tr style="color:var(--emerald);"><td>Total Revenue (+)</td><td style="text-align:right">₹${reportData.executive.total_revenue.toFixed(2)}</td></tr>
                <tr style="color:var(--danger);"><td>Total Expenses (-)</td><td style="text-align:right">₹${reportData.executive.total_expenses.toFixed(2)}</td></tr>
                <tr style="color:var(--danger);"><td>Total Returns (-)</td><td style="text-align:right">₹${(reportData.executive.total_returns || 0).toFixed(2)}</td></tr>
                <tr style="background:#ecfdf5; font-weight:bold; font-size:1.1em; color:var(--emerald);">
                    <td>Final Net Profit (=)</td>
                    <td style="text-align:right">₹${reportData.executive.net_profit.toFixed(2)}</td>
                </tr>
            </table>
        `;
    } else if (type === 'patients') {
        title = 'Patient Visit Summary';
        html = `<p>Total distinct patient visits recorded for the selected period: <strong>${reportData.executive.total_patients}</strong></p>`;
    } else if (type === 'consultations') {
        title = 'Consultations Summary';
        html = `<p>Total consultations handled by doctors: <strong>${reportData.executive.total_consultations}</strong></p>`;
    } else if (type === 'returns') {
        title = 'Returns Breakdown';
        html = `
            <table class="data-table">
                <tr><th>Date</th><th>Patient</th><th>Item</th><th>Returned Qty</th><th>Refunded Amount</th></tr>
                ${(reportData.returns && reportData.returns.length > 0) ? reportData.returns.map(r => `<tr><td>${r.return_date || ''}</td><td>${r.patient_name || '-'}</td><td>${r.medicine_name}</td><td>${r.returned_qty}</td><td style="color:var(--danger)">-₹${(r.return_amount || 0).toFixed(2)}</td></tr>`).join('') : '<tr><td colspan="5" style="text-align:center;">No returns recorded</td></tr>'}
            </table>
        `;
    } else if (type === 'cleared') {
        title = 'Doctor Fee Breakdown';
        html = `<p>Total Consultation Fee Revenue generated by ${type === 'gents' ? 'Gents' : 'Ladies'} Doctors: <strong>₹${type === 'gents' ? (reportData.executive.gents_doctor_revenue || 0).toFixed(2) : (reportData.executive.ladies_doctor_revenue || 0).toFixed(2)}</strong></p>`;
    } else if (type === 'cleared') {
        title = 'Cleared Pending Payments';
        html = `<p>Total pending amounts that were successfully cleared by patients during this period: <strong>₹${(reportData.executive.cleared_pending || 0).toFixed(2)}</strong></p>`;
    }

    document.getElementById('reportDetailTitle').textContent = title;
    document.getElementById('reportDetailContent').innerHTML = html;
    openModal('reportDetailModal');
}

// ═══════════════════════════════════════════
// AUTO BACKUP LOGIC
// ═══════════════════════════════════════════
function saveAutoBackupSettings() {
    const wa = document.getElementById('settingWaNumber').value.trim();
    const time = document.getElementById('settingBackupTime').value;
    
    if (wa) localStorage.setItem('whatsapp_backup_number', wa);
    if (time) localStorage.setItem('auto_backup_time', time);
    
    closeModal('autoBackupSettingsModal');
    toast('Auto Backup Settings Saved Successfully!');
}

// Check every minute if it's time to backup
setInterval(() => {
    const backupTime = localStorage.getItem('auto_backup_time');
    const waNumber = localStorage.getItem('whatsapp_backup_number');
    
    if (backupTime && waNumber) {
        const now = new Date();
        const currentHours = String(now.getHours()).padStart(2, '0');
        const currentMins = String(now.getMinutes()).padStart(2, '0');
        const currentTime = `${currentHours}:${currentMins}`;
        
        const lastBackupDate = localStorage.getItem('last_auto_backup_date');
        const todayStr = now.toISOString().split('T')[0];
        
        if (currentTime === backupTime && lastBackupDate !== todayStr) {
            console.log("Triggering Auto Backup for time:", backupTime);
            localStorage.setItem('last_auto_backup_date', todayStr);
            
            // Run full backup
            exportReportCustom('custom', '2000-01-01', todayStr, 'whatsapp');
        }
    }
}, 60000);

// Populate settings on load
document.addEventListener('DOMContentLoaded', () => {
    const wa = localStorage.getItem('whatsapp_backup_number');
    const time = localStorage.getItem('auto_backup_time');
    if (wa && document.getElementById('settingWaNumber')) document.getElementById('settingWaNumber').value = wa;
    if (time && document.getElementById('settingBackupTime')) document.getElementById('settingBackupTime').value = time;
});
