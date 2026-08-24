<?php
$page_title = 'พิมพ์บาร์โค้ด & สแกนเนอร์ - STOCKPRO';
require_once 'db.php';
require_once 'header.php';

$products = $pdo->query("SELECT id, sku, barcode, name, sell_price, location_zone FROM products ORDER BY name ASC")->fetchAll();
?>

<div class="container-fluid px-lg-4 py-4 no-print">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-upc-scan text-primary me-2"></i>ระบบบาร์โค้ดและสแกนเนอร์ (Barcode Terminal)</h3>
            <p class="text-muted small mb-0">พิมพ์ป้ายบาร์โค้ดติดสินค้า และยิงสแกนค้นหาสินค้าเพื่อรับเข้า/เบิกจ่ายได้ทันที</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- กล้องสแกนเนอร์ -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                <h5 class="fw-bold mb-3"><i class="bi bi-camera me-2 text-primary"></i>สแกนบาร์โค้ดผ่านกล้อง</h5>
                <div id="reader" style="width: 100%; min-height: 250px;" class="rounded-3 bg-light border mb-3"></div>
                <div id="scanResult" class="alert alert-info py-2 small d-none"></div>

                <div class="mt-2 text-start">
                    <label class="form-label small fw-semibold">หรือพิมพ์ค้นหาด้วยรหัส SKU / Barcode</label>
                    <div class="input-group">
                        <input type="text" id="manualScanInput" class="form-control" placeholder="ยิงหรือพิมพ์รหัสที่นี่..." onkeydown="if(event.key==='Enter'){event.preventDefault();redirectToStockForm();}">
                        <button class="btn btn-primary" type="button" onclick="redirectToStockForm()"><i class="bi bi-arrow-right"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- รายการสินค้า + เลือกพิมพ์ป้าย -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-3 px-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h6 class="fw-bold mb-0"><i class="bi bi-printer me-2"></i>พิมพ์ป้ายบาร์โค้ดติดสินค้า (Label Print)</h6>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <div class="input-group input-group-sm" style="width:auto;">
                                <span class="input-group-text">จำนวนป้าย/ชิ้น</span>
                                <input type="number" id="defaultQty" class="form-control" value="1" min="1" max="50" style="width:70px;" title="ค่าเริ่มต้นเมื่อติ๊กเลือก">
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAll(true)">
                                <i class="bi bi-check2-square me-1"></i>เลือกทั้งหมด
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAll(false)">
                                ยกเลิก
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="printSelected()">
                                <i class="bi bi-printer me-1"></i>พิมพ์ที่เลือก
                                <span id="selectedCount" class="badge bg-light text-dark ms-1">0</span>
                            </button>
                        </div>
                    </div>
                    <p class="small text-muted mb-0 mt-2">ติ๊กเลือกสินค้าที่ต้องการ · กำหนดจำนวนป้ายแต่ละรายการ · กดพิมพ์ที่เลือก</p>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-3" style="width:42px;">
                                        <input type="checkbox" class="form-check-input" id="checkAll" onclick="selectAll(this.checked)" title="เลือกทั้งหมด">
                                    </th>
                                    <th>สินค้า</th>
                                    <th>พิกัด</th>
                                    <th class="text-center">บาร์โค้ด</th>
                                    <th class="text-center" style="width:90px;">จำนวน</th>
                                    <th class="pe-3 text-end" style="width:100px;">พิมพ์เดี่ยว</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted">ยังไม่มีสินค้าในระบบ</td></tr>
                                <?php else: ?>
                                    <?php foreach ($products as $pr):
                                        $code = !empty($pr['barcode']) ? $pr['barcode'] : $pr['sku'];
                                    ?>
                                    <tr class="label-row"
                                        data-id="<?= (int)$pr['id'] ?>"
                                        data-name="<?= htmlspecialchars($pr['name'], ENT_QUOTES) ?>"
                                        data-sku="<?= htmlspecialchars($pr['sku'], ENT_QUOTES) ?>"
                                        data-code="<?= htmlspecialchars($code, ENT_QUOTES) ?>"
                                        data-zone="<?= htmlspecialchars($pr['location_zone'] ?? 'Zone A', ENT_QUOTES) ?>"
                                        data-price="<?= number_format((float)$pr['sell_price'], 2) ?>">
                                        <td class="ps-3">
                                            <input type="checkbox" class="form-check-input row-check" onchange="updateSelectedCount()">
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($pr['name']) ?></div>
                                            <span class="small text-muted">[SKU: <?= htmlspecialchars($pr['sku']) ?>]</span>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($pr['location_zone'] ?? 'Zone A') ?></span></td>
                                        <td class="text-center">
                                            <svg class="barcode-svg" jsbarcode-format="CODE128"
                                                 jsbarcode-value="<?= htmlspecialchars($code) ?>"
                                                 jsbarcode-textmargin="0" jsbarcode-fontoptions="bold"
                                                 jsbarcode-height="32" jsbarcode-width="1.4"
                                                 jsbarcode-displayvalue="true"></svg>
                                        </td>
                                        <td class="text-center">
                                            <input type="number" class="form-control form-control-sm row-qty mx-auto"
                                                   value="1" min="1" max="50" style="width:70px;"
                                                   onclick="this.closest('tr').querySelector('.row-check').checked=true;updateSelectedCount();">
                                        </td>
                                        <td class="pe-3 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary" title="พิมพ์ป้ายรายการนี้"
                                                    onclick="printSingle(this)">
                                                <i class="bi bi-printer"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- พื้นที่พิมพ์ป้าย (แสดงเฉพาะตอน print) -->
<div id="printArea" class="print-only"></div>

<style>
/* หน้าจอ: ซ่อนพื้นที่พิมพ์ */
.print-only { display: none; }

@media print {
    body * { visibility: hidden !important; }
    .print-only, .print-only * { visibility: visible !important; }
    .print-only {
        display: block !important;
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 0;
        margin: 0;
    }
    .no-print, nav, footer, .navbar, main > .container-fluid.no-print {
        display: none !important;
    }
    @page {
        margin: 8mm;
        size: auto;
    }
    .label-sheet {
        display: flex;
        flex-wrap: wrap;
        gap: 4mm;
        justify-content: flex-start;
        align-content: flex-start;
    }
    .label-item {
        width: 62mm;
        min-height: 32mm;
        border: 1px solid #333;
        border-radius: 2mm;
        padding: 2.5mm 3mm;
        page-break-inside: avoid;
        break-inside: avoid;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-sizing: border-box;
        background: #fff;
        color: #000;
    }
    .label-item .label-name {
        font-size: 10pt;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 1mm;
        max-height: 2.4em;
        overflow: hidden;
    }
    .label-item .label-meta {
        font-size: 7.5pt;
        color: #333;
        margin-bottom: 1mm;
    }
    .label-item .label-barcode {
        text-align: center;
    }
    .label-item .label-barcode svg {
        max-width: 100%;
        height: auto;
    }
    .label-item .label-price {
        font-size: 9pt;
        font-weight: 700;
        text-align: right;
        margin-top: 1mm;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
window.addEventListener('DOMContentLoaded', () => {
    JsBarcode(".barcode-svg").init();

    if (document.getElementById('reader')) {
        try {
            const html5QrCode = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
            html5QrCode.render((decodedText) => {
                const el = document.getElementById('scanResult');
                el.classList.remove('d-none');
                el.innerText = "พบรหัส: " + decodedText;
                window.location.href = "stock_form.php?search_sku=" + encodeURIComponent(decodedText);
            });
        } catch (e) {
            console.warn('Camera scanner unavailable', e);
        }
    }

    // เมื่อเปลี่ยนจำนวนเริ่มต้น → อัปเดตช่องที่ยังเป็นค่าเดิม
    document.getElementById('defaultQty')?.addEventListener('change', function () {
        const q = Math.max(1, Math.min(50, parseInt(this.value, 10) || 1));
        this.value = q;
    });
});

function redirectToStockForm() {
    const val = document.getElementById('manualScanInput').value.trim();
    if (val) {
        window.location.href = "stock_form.php?search_sku=" + encodeURIComponent(val);
    }
}

function selectAll(checked) {
    document.querySelectorAll('.row-check').forEach(cb => { cb.checked = !!checked; });
    const all = document.getElementById('checkAll');
    if (all) all.checked = !!checked;
    if (checked) {
        const q = Math.max(1, parseInt(document.getElementById('defaultQty').value, 10) || 1);
        document.querySelectorAll('.row-qty').forEach(inp => {
            if (!inp.value || parseInt(inp.value, 10) < 1) inp.value = q;
        });
    }
    updateSelectedCount();
}

function updateSelectedCount() {
    const n = document.querySelectorAll('.row-check:checked').length;
    const badge = document.getElementById('selectedCount');
    if (badge) badge.textContent = n;
    const all = document.getElementById('checkAll');
    const total = document.querySelectorAll('.row-check').length;
    if (all) all.checked = total > 0 && n === total;
}

function rowData(tr) {
    return {
        name: tr.dataset.name || '',
        sku: tr.dataset.sku || '',
        code: tr.dataset.code || '',
        zone: tr.dataset.zone || '',
        price: tr.dataset.price || '0.00',
        qty: Math.max(1, Math.min(50, parseInt(tr.querySelector('.row-qty')?.value, 10) || 1))
    };
}

function buildLabelHtml(item) {
    // สร้างป้ายซ้ำตามจำนวน
    let html = '';
    for (let i = 0; i < item.qty; i++) {
        html += `
        <div class="label-item">
            <div class="label-name">${escapeHtml(item.name)}</div>
            <div class="label-meta">SKU: ${escapeHtml(item.sku)} · ${escapeHtml(item.zone)}</div>
            <div class="label-barcode">
                <svg class="print-barcode" data-code="${escapeHtml(item.code)}"></svg>
            </div>
            <div class="label-price">฿${escapeHtml(item.price)}</div>
        </div>`;
    }
    return html;
}

function escapeHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function renderAndPrint(items) {
    if (!items.length) {
        alert('กรุณาเลือกสินค้าอย่างน้อย 1 รายการ');
        return;
    }
    const area = document.getElementById('printArea');
    let sheet = '<div class="label-sheet">';
    items.forEach(item => { sheet += buildLabelHtml(item); });
    sheet += '</div>';
    area.innerHTML = sheet;

    // สร้างบาร์โค้ดในพื้นที่พิมพ์
    area.querySelectorAll('.print-barcode').forEach(svg => {
        try {
            JsBarcode(svg, svg.getAttribute('data-code'), {
                format: 'CODE128',
                width: 1.3,
                height: 36,
                displayValue: true,
                fontSize: 11,
                margin: 2,
                textMargin: 1
            });
        } catch (e) {
            console.warn('Barcode error', e);
        }
    });

    // พิมพ์หลังเรนเดอร์สั้น ๆ
    setTimeout(() => {
        window.print();
    }, 200);
}

function printSelected() {
    const items = [];
    document.querySelectorAll('.label-row').forEach(tr => {
        const cb = tr.querySelector('.row-check');
        if (cb && cb.checked) {
            items.push(rowData(tr));
        }
    });
    renderAndPrint(items);
}

function printSingle(btn) {
    const tr = btn.closest('.label-row');
    if (!tr) return;
    // ติ๊กและใช้จำนวนในแถวนั้น
    const cb = tr.querySelector('.row-check');
    if (cb) cb.checked = true;
    updateSelectedCount();
    renderAndPrint([rowData(tr)]);
}
</script>

<?php require_once 'footer.php'; ?>
