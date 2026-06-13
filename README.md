# PharmaFlow Pharmacy Management System

A local PHP, HTML, CSS, and JavaScript pharmacy management starter with these working modules:

- Dashboard with daily sales, purchases, revenue snapshot, low stock, expiring batches, out of stock, recent transactions, and user activity logs
- Drugs/products module with add, edit, delete, categories, brands, dosage, units, barcode, pricing, manufacturer, storage condition, sell mode, and minimum stock alerts
- Inventory module with stock-in purchases, stock-out adjustments, damaged/expired handling, batch tracking, expiry monitoring, stock history, and multi-batch support
- Sales/POS module with fast search, multi-item cart, discount, tax, cash, MTN MoMo, Airtel Money, partial payments, receipt printing, and invoice generation
- Patient management with registration, contact details, next of kin, prescription history, loyalty discount, and purchase history
- User management with roles for admin, pharmacist, cashier, and director plus activity logs

## Run locally

1. Open the project folder in XAMPP `htdocs` or any PHP-enabled web root.
2. Start Apache.
3. Visit `http://localhost/pharmacy/index.php`

You can also run it quickly with the PHP built-in server from this folder:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/index.php`.

## Notes

- The app auto-creates `data/pharmacy.sqlite` on first run and seeds demo data.
- This is a strong operational starter for the modules you listed. We can next add authentication, supplier management, prescriptions, reports, procurement approvals, and real mobile money integrations.
