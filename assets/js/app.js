const searchInput = document.getElementById('productSearch');
const productGrid = document.getElementById('productGrid');
const categoryFilter = document.getElementById('categoryFilter');
const categoryButtons = document.querySelectorAll('[data-category-filter]');
const paymentModeInput = document.getElementById('posPaymentMode');
const mobileField = document.getElementById('posMobileField');
const discountInput = document.getElementById('posDiscountInput');
const taxInput = document.getElementById('posTaxInput');
const paidAmountInput = document.getElementById('posPaidAmount');
const totalPreview = document.getElementById('posTotalPreview');
const taxPreview = document.getElementById('posTaxPreview');
const discountPreview = document.getElementById('posDiscountPreview');
const paymentStatus = document.getElementById('posPaymentStatus');
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const loginClock = document.getElementById('loginClock');
const loginGreeting = document.getElementById('loginGreeting');

const formatMoney = (value) => `UGX ${Math.max(0, Number(value || 0)).toLocaleString()}`;

if (productGrid) {
  const filterTiles = () => {
    const term = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const category = categoryFilter ? categoryFilter.value.trim().toLowerCase() : '';

    productGrid.querySelectorAll('.pos-product-card').forEach((tile) => {
      const haystack = tile.dataset.search || '';
      const tileCategory = tile.dataset.category || '';
      const matchesTerm = !term || haystack.includes(term);
      const matchesCategory = !category || tileCategory === category;
      tile.style.display = matchesTerm && matchesCategory ? 'flex' : 'none';
    });
  };

  searchInput?.addEventListener('input', filterTiles);
  categoryFilter?.addEventListener('change', filterTiles);
  categoryButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (categoryFilter) {
        categoryFilter.value = button.dataset.categoryFilter || '';
      }
      categoryButtons.forEach((item) => item.classList.remove('active'));
      button.classList.add('active');
      filterTiles();
    });
  });
}

if (paymentModeInput && mobileField) {
  const syncPaymentMode = () => {
    const requiresMobile = ['MTN MoMo', 'Airtel Money'].includes(paymentModeInput.value);
    mobileField.hidden = !requiresMobile;
  };

  paymentModeInput.addEventListener('change', syncPaymentMode);
  syncPaymentMode();
}

if (totalPreview && paidAmountInput && discountInput && taxInput && paymentStatus) {
  const subtotal = Number(totalPreview.dataset.subtotal || 0);

  const updatePaymentSummary = () => {
    const discount = Math.max(0, Number(discountInput.value || 0));
    const tax = Math.max(0, Number(taxInput.value || 0));
    const paid = Math.max(0, Number(paidAmountInput.value || 0));
    const total = Math.max(0, subtotal - discount + tax);
    const remaining = Math.max(0, total - paid);

    totalPreview.textContent = formatMoney(total);
    taxPreview.textContent = formatMoney(tax);
    discountPreview.textContent = formatMoney(discount);

    if (paid <= 0) {
      paymentStatus.innerHTML = `<strong>No payment entered.</strong><span>Customer has a balance of ${formatMoney(total)}.</span>`;
      paymentStatus.className = 'pos-status-card warning';
      return;
    }

    if (paid < total) {
      paymentStatus.innerHTML = `<strong>Customer paid less.</strong><span>Remaining balance is ${formatMoney(remaining)}.</span>`;
      paymentStatus.className = 'pos-status-card warning';
      return;
    }

    paymentStatus.innerHTML = '<strong>Customer is fully paid.</strong><span>No balance remaining.</span>';
    paymentStatus.className = 'pos-status-card success';
  };

  [discountInput, taxInput, paidAmountInput].forEach((input) => {
    input.addEventListener('input', updatePaymentSummary);
  });

  updatePaymentSummary();
}

if (menuToggle && sidebar) {
  menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    menuToggle.setAttribute('aria-expanded', sidebar.classList.contains('open') ? 'true' : 'false');
  });
}

const buildPrintDocument = (markup, title) => `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${title}</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 24px; color: #17342d; }
    .receipt { max-width: 780px; margin: 0 auto; }
    .receipt-brand { text-align: center; margin-bottom: 16px; }
    .receipt-logo { width: 74px; height: 74px; object-fit: cover; border-radius: 14px; display: block; margin: 0 auto 8px; }
    .receipt-header-split { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin: 16px 0; }
    .receipt-customer-box { border: 1px solid #d8e5df; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; }
    .receipt-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .receipt-table th, .receipt-table td { padding: 10px 8px; border-bottom: 1px solid #d8e5df; }
    .align-center { text-align: center; }
    .align-right { text-align: right; }
    .receipt-serial { text-align: center; margin: 14px 0; }
    .receipt-serial strong { display: inline-block; background: #dff6ef; color: #0f6b59; padding: 8px 16px; border-radius: 999px; }
    .receipt-totals { display: grid; gap: 8px; margin-top: 18px; justify-items: end; }
    .receipt-footer-message { text-align: center; font-weight: 700; margin-top: 20px; color: #0f6b59; }
  </style>
</head>
<body>${markup}</body>
</html>`;

const openPrintPopup = (receipt, title, autoPrint = true) => {
  const popup = window.open('', '_blank', 'width=900,height=800');
  if (!popup) {
    return;
  }

  popup.document.open();
  popup.document.write(buildPrintDocument(receipt.outerHTML, title));
  popup.document.close();
  popup.focus();
  if (autoPrint) {
    popup.onload = () => popup.print();
  }
};

document.querySelectorAll('[data-download-receipt]').forEach((button) => {
  button.addEventListener('click', () => {
    const targetId = button.getAttribute('data-download-receipt');
    const receipt = targetId ? document.getElementById(targetId) : null;
    const documentTitle = button.getAttribute('data-document-title') || 'invoice';
    if (!receipt) {
      return;
    }

    const html = buildPrintDocument(receipt.outerHTML, documentTitle);
    const blob = new Blob([html], { type: 'text/html' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${documentTitle}-${Date.now()}.html`;
    link.click();
    URL.revokeObjectURL(link.href);
  });
});

document.querySelectorAll('[data-print-receipt]').forEach((button) => {
  button.addEventListener('click', () => {
    const targetId = button.getAttribute('data-print-receipt');
    const printTitle = button.getAttribute('data-print-title') || 'Receipt';
    const receipt = targetId ? document.getElementById(targetId) : null;
    if (!receipt) {
      return;
    }
    openPrintPopup(receipt, printTitle);
  });
});

const autoPrintReceipt = document.getElementById('receiptArea');
if (autoPrintReceipt && autoPrintReceipt.dataset.autoprint === '1') {
  const printTitle = autoPrintReceipt.classList.contains('receipt-invoice') ? 'Invoice' : 'Receipt';
  window.setTimeout(() => openPrintPopup(autoPrintReceipt, printTitle, true), 300);
}

if (loginClock && loginGreeting) {
  const updateLoginClock = () => {
    const now = new Date();
    const hour = now.getHours();
    let greeting = 'Good evening';
    if (hour < 12) {
      greeting = 'Good morning';
    } else if (hour < 17) {
      greeting = 'Good afternoon';
    }

    loginGreeting.textContent = `${greeting}, welcome back`;
    loginClock.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  };

  updateLoginClock();
  window.setInterval(updateLoginClock, 1000);
}
