<?php

return [
    'sap_screen' => [
        // Hoy usamos "public" para exponer archivos; en futuro puede cambiarse a "s3" por env.
        'disk' => env('BULK_SAP_SCREEN_DISK', 'public'),
        'path' => env('BULK_SAP_SCREEN_PATH', 'sap-screen'),
    ],

    'upload_support' => [
        // Hoy usamos "public" para exponer archivos; en futuro puede cambiarse a "s3" por env.
        'disk' => env('BULK_UPLOAD_SUPPORT_DISK', 'public'),
        'path' => env('BULK_UPLOAD_SUPPORT_PATH', 'request-support'),
    ],

    'attachments' => [
        // 'url'   = fileUrl/previewUrl apuntan directo a S3 (menos ancho de banda, pero algunos firewalls
        //           corporativos bloquean el dominio de S3 y el cliente no puede abrir el archivo).
        // 'proxy' = el backend descarga de S3 y sirve el archivo en su propia response (mismo dominio del
        //           back, evita el bloqueo de firewall, pero consume más ancho de banda del servidor).
        'delivery_mode' => env('ATTACHMENT_DELIVERY_MODE', 'url'),
    ],

    'new_request' => [
        'modules' => [
            1 => [
                'slug' => 'credits',
                'fields' => [
                    ['name' => 'requestDate', 'aliases' => ['requestdate', 'date'], 'required' => false],
                    ['name' => 'customerId', 'aliases' => ['customerid', 'customernumber'], 'required' => true],
                    ['name' => 'area', 'aliases' => ['area'], 'required' => false],
                    ['name' => 'reasonId', 'aliases' => ['reasonid', 'reasonwhy', 'reason', 'requestreason'], 'required' => true],
                    ['name' => 'classificationId', 'aliases' => ['classificationid', 'classification', 'requestclassification'], 'required' => true],
                    ['name' => 'orderNumber', 'aliases' => ['ordernumber'], 'required' => false],
                    ['name' => 'deliveryNote', 'aliases' => ['deliverynote'], 'required' => false],
                    ['name' => 'invoiceNumber', 'aliases' => ['invoicenumber'], 'required' => false],
                    ['name' => 'invoiceDate', 'aliases' => ['invoicedate'], 'required' => false],
                    ['name' => 'currency', 'aliases' => ['currency'], 'required' => true],
                    ['name' => 'amount', 'aliases' => ['amount'], 'required' => true],
                    ['name' => 'hasIva', 'aliases' => ['hasiva', 'iva'], 'required' => false],
                    ['name' => 'creditNumber', 'aliases' => ['creditnumber'], 'required' => false],
                    ['name' => 'comments', 'aliases' => ['comments', 'comment', 'comentarios', 'comentario'], 'required' => false],
                ],
            ],
            2 => [
                'slug' => 'debits',
                'fields' => [
                    ['name' => 'requestDate', 'aliases' => ['requestdate', 'date'], 'required' => false],
                    ['name' => 'customerId', 'aliases' => ['customerid', 'customernumber'], 'required' => true],
                    ['name' => 'area', 'aliases' => ['area'], 'required' => false],
                    ['name' => 'reasonId', 'aliases' => ['reasonid', 'reasonwhy', 'reason', 'requestreason'], 'required' => true],
                    ['name' => 'classificationId', 'aliases' => ['classificationid', 'classification', 'requestclassification'], 'required' => true],
                    ['name' => 'orderNumber', 'aliases' => ['ordernumber'], 'required' => false],
                    ['name' => 'deliveryNote', 'aliases' => ['deliverynote'], 'required' => false],
                    ['name' => 'invoiceNumber', 'aliases' => ['invoicenumber'], 'required' => false],
                    ['name' => 'invoiceDate', 'aliases' => ['invoicedate'], 'required' => false],
                    ['name' => 'currency', 'aliases' => ['currency'], 'required' => true],
                    ['name' => 'amount', 'aliases' => ['amount'], 'required' => true],
                    ['name' => 'hasIva', 'aliases' => ['hasiva', 'iva'], 'required' => false],
                    ['name' => 'creditNumber', 'aliases' => ['creditnumber'], 'required' => false],
                    ['name' => 'comments', 'aliases' => ['comments', 'comment', 'comentarios', 'comentario'], 'required' => false],
                ],
            ],
            3 => [
                'slug' => 'auditor-credits',
                'fields' => [
                    ['name' => 'requestDate', 'aliases' => ['requestdate', 'date'], 'required' => false],
                    ['name' => 'customerId', 'aliases' => ['customerid', 'customernumber'], 'required' => true],
                    ['name' => 'area', 'aliases' => ['area'], 'required' => false],
                    ['name' => 'reasonId', 'aliases' => ['reasonid', 'reasonwhy', 'reason', 'requestreason'], 'required' => true],
                    ['name' => 'classificationId', 'aliases' => ['classificationid', 'classification', 'requestclassification'], 'required' => true],
                    ['name' => 'orderNumber', 'aliases' => ['ordernumber'], 'required' => false],
                    ['name' => 'deliveryNote', 'aliases' => ['deliverynote'], 'required' => false],
                    ['name' => 'invoiceNumber', 'aliases' => ['invoicenumber'], 'required' => false],
                    ['name' => 'invoiceDate', 'aliases' => ['invoicedate'], 'required' => false],
                    ['name' => 'currency', 'aliases' => ['currency'], 'required' => true],
                    ['name' => 'amount', 'aliases' => ['amount'], 'required' => true],
                    ['name' => 'hasIva', 'aliases' => ['hasiva', 'iva'], 'required' => false],
                    ['name' => 'creditNumber', 'aliases' => ['creditnumber'], 'required' => false],
                    ['name' => 'comments', 'aliases' => ['comments', 'comment', 'comentarios', 'comentario'], 'required' => false],
                ],
            ],
            4 => [
                'slug' => 'auditor-debits',
                'fields' => [
                    ['name' => 'requestDate', 'aliases' => ['requestdate', 'date'], 'required' => false],
                    ['name' => 'customerId', 'aliases' => ['customerid', 'customernumber'], 'required' => true],
                    ['name' => 'area', 'aliases' => ['area'], 'required' => false],
                    ['name' => 'reasonId', 'aliases' => ['reasonid', 'reasonwhy', 'reason', 'requestreason'], 'required' => true],
                    ['name' => 'classificationId', 'aliases' => ['classificationid', 'classification', 'requestclassification'], 'required' => true],
                    ['name' => 'orderNumber', 'aliases' => ['ordernumber'], 'required' => false],
                    ['name' => 'deliveryNote', 'aliases' => ['deliverynote'], 'required' => false],
                    ['name' => 'invoiceNumber', 'aliases' => ['invoicenumber'], 'required' => false],
                    ['name' => 'invoiceDate', 'aliases' => ['invoicedate'], 'required' => false],
                    ['name' => 'currency', 'aliases' => ['currency'], 'required' => true],
                    ['name' => 'amount', 'aliases' => ['amount'], 'required' => true],
                    ['name' => 'hasIva', 'aliases' => ['hasiva', 'iva'], 'required' => false],
                    ['name' => 'creditNumber', 'aliases' => ['creditnumber'], 'required' => false],
                    ['name' => 'comments', 'aliases' => ['comments', 'comment', 'comentarios', 'comentario'], 'required' => false],
                ],
            ],
            5 => [
                'slug' => 're-billing',
                'fields' => [
                    ['name' => 'requestDate', 'aliases' => ['requestdate', 'date'], 'required' => false],
                    ['name' => 'customerId', 'aliases' => ['customerid', 'customernumber'], 'required' => true],
                    ['name' => 'area', 'aliases' => ['area'], 'required' => false],
                    ['name' => 'reasonId', 'aliases' => ['reasonid', 'reasonwhy', 'reason', 'requestreason'], 'required' => true],
                    ['name' => 'classificationId', 'aliases' => ['classificationid', 'classification', 'requestclassification'], 'required' => true],
                    ['name' => 'orderNumber', 'aliases' => ['ordernumber'], 'required' => false],
                    ['name' => 'deliveryNote', 'aliases' => ['deliverynote'], 'required' => false],
                    ['name' => 'invoiceNumber', 'aliases' => ['invoicenumber'], 'required' => false],
                    ['name' => 'invoiceDate', 'aliases' => ['invoicedate'], 'required' => false],
                    ['name' => 'currency', 'aliases' => ['currency'], 'required' => true],
                    ['name' => 'amount', 'aliases' => ['amount'], 'required' => true],
                    ['name' => 'hasIva', 'aliases' => ['hasiva', 'iva'], 'required' => false],
                    ['name' => 'creditDebitRefId', 'aliases' => ['creditdebitrefid', 'creditdebitid'], 'required' => true],
                    ['name' => 'newInvoice', 'aliases' => ['newinvoice'], 'required' => true],
                    ['name' => 'creditNumber', 'aliases' => ['creditnumber'], 'required' => false],
                    ['name' => 'comments', 'aliases' => ['comments', 'comment', 'comentarios', 'comentario'], 'required' => false],
                ],
            ],
            6 => [
                'slug' => 'material-return',
                'fields' => [
                    ['name' => 'requestDate', 'aliases' => ['requestdate', 'date'], 'required' => false],
                    ['name' => 'customerId', 'aliases' => ['customerid', 'customernumber'], 'required' => true],
                    ['name' => 'area', 'aliases' => ['area'], 'required' => false],
                    ['name' => 'reasonId', 'aliases' => ['reasonid', 'reasonwhy', 'reason', 'requestreason'], 'required' => true],
                    ['name' => 'classificationId', 'aliases' => ['classificationid', 'classification', 'requestclassification'], 'required' => true],
                    ['name' => 'orderNumber', 'aliases' => ['ordernumber'], 'required' => false],
                    ['name' => 'deliveryNote', 'aliases' => ['deliverynote'], 'required' => false],
                    ['name' => 'invoiceNumber', 'aliases' => ['invoicenumber'], 'required' => false],
                    ['name' => 'invoiceDate', 'aliases' => ['invoicedate'], 'required' => false],
                    ['name' => 'warehouseCode', 'aliases' => ['warehousecode'], 'required' => true],
                    ['name' => 'currency', 'aliases' => ['currency'], 'required' => true],
                    ['name' => 'replenishmentAmount', 'aliases' => ['replenishmentamount'], 'required' => true],
                    ['name' => 'hasReplenishmentIva', 'aliases' => ['hasreplenishmentiva', 'replenishmentiva'], 'required' => false],
                    ['name' => 'warehouseAmount', 'aliases' => ['warehouseamount'], 'required' => true],
                    ['name' => 'hasWarehouseIva', 'aliases' => ['haswarehouseiva', 'warehouseiva'], 'required' => false],
                    ['name' => 'sapReturnOrder', 'aliases' => ['sapreturnorder'], 'required' => true],
                    ['name' => 'hasRga', 'aliases' => ['hasrga', 'rga'], 'required' => false],
                    ['name' => 'creditNumber', 'aliases' => ['creditnumber'], 'required' => false],
                    ['name' => 'comments', 'aliases' => ['comments', 'comment', 'comentarios', 'comentario'], 'required' => false],
                ],
            ],
        ],
    ],

    'users' => [
        'default_password' => env('BULK_USERS_DEFAULT_PASSWORD', 'ChangeMe123!'),
    ],
];
