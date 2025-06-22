<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/erp_project/css/style.css"> <!-- Adjust path if necessary -->
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <a class="navbar-brand" href="/erp_project/index.php">ERP System</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" href="/erp_project/index.php">Home</a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navClients" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Clients
                </a>
                <div class="dropdown-menu" aria-labelledby="navClients">
                    <a class="dropdown-item" href="/erp_project/modules/clients/client_add.php">Add Client</a>
                    <a class="dropdown-item" href="/erp_project/modules/clients/client_list.php">View Clients</a>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navManufacturers" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Manufacturers
                </a>
                <div class="dropdown-menu" aria-labelledby="navManufacturers">
                    <a class="dropdown-item" href="/erp_project/modules/manufacturers/manufacturer_add.php">Add Manufacturer</a>
                    <a class="dropdown-item" href="/erp_project/modules/manufacturers/manufacturer_list.php">View Manufacturers</a>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navSuppliers" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Suppliers
                </a>
                <div class="dropdown-menu" aria-labelledby="navSuppliers">
                    <a class="dropdown-item" href="/erp_project/modules/suppliers/supplier_add.php">Add Supplier</a>
                    <a class="dropdown-item" href="/erp_project/modules/suppliers/supplier_list.php">View Suppliers</a>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navSizes" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Sizes
                </a>
                <div class="dropdown-menu" aria-labelledby="navSizes">
                    <a class="dropdown-item" href="/erp_project/modules/sizes/size_add.php">Add Size</a>
                    <a class="dropdown-item" href="/erp_project/modules/sizes/size_list.php">View Sizes</a>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navProducts" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Products
                </a>
                <div class="dropdown-menu" aria-labelledby="navProducts">
                    <a class="dropdown-item" href="/erp_project/modules/products/product_add.php">Add Product(s)</a>
                    <a class="dropdown-item" href="/erp_project/modules/products/product_list.php">View Products</a>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navBanks" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Banks
                </a>
                <div class="dropdown-menu" aria-labelledby="navBanks">
                    <a class="dropdown-item" href="/erp_project/modules/banks/bank_add.php">Add Bank</a>
                    <a class="dropdown-item" href="/erp_project/modules/banks/bank_list.php">View Banks</a>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navExporters" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Exporters
                </a>
                <div class="dropdown-menu" aria-labelledby="navExporters">
                    <a class="dropdown-item" href="/erp_project/modules/exporters/exporter_add.php">Add Exporter</a>
                    <a class="dropdown-item" href="/erp_project/modules/exporters/exporter_list.php">View Exporters</a>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navPerformaInvoices" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Performa Invoices
                </a>
                <div class="dropdown-menu" aria-labelledby="navPerformaInvoices">
                    <a class="dropdown-item" href="/erp_project/modules/performa_invoices/performa_invoice_add.php">Add Performa Invoice</a>
                    <a class="dropdown-item" href="/erp_project/modules/performa_invoices/performa_invoice_list.php">View Performa Invoices</a>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navPurchaseOrders" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Purchase Orders
                </a>
                <div class="dropdown-menu" aria-labelledby="navPurchaseOrders">
                    <a class="dropdown-item" href="/erp_project/modules/purchase_orders/purchase_order_list.php">View Purchase Orders</a>
                    <!-- <a class="dropdown-item" href="/erp_project/modules/purchase_orders/purchase_order_add.php">Add PO Manually</a> --> <!-- Future option -->
                </div>
            </li>
            <!-- Add more menus here as modules are developed -->
        </ul>
    </div>
</nav>

<div class="container mt-4"> <!-- Main content container -->
