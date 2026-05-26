<?php

return [
    'source_connection' => env('PRODUCTION_SYNC_SOURCE_CONNECTION', env('DB_CONNECTION', 'pgsql')),
    'target_connection' => env('PRODUCTION_SYNC_TARGET_CONNECTION', 'production_ocn_erp'),

    /*
    |----------------------------------------------------------------------
    | Ordered table map for staged production import
    |----------------------------------------------------------------------
    |
    | Import runs in the order below. Each module can be synced separately
    | so cutover can proceed one business flow at a time with reconciliation
    | after every step.
    |
    */
    'modules' => [
        'core' => [
            'companies',
            'users',
            'accounts',
            'document_sequences',
            'coa_settings',
            'cash_categories',
            'category_coa_mappings',
            'payment_methods',
            'payment_method_sales_channels',
            'erp_settings',
            'label_profiles',
        ],
        'inventory_master' => [
            'warehouses',
            'product_categories',
            'uoms',
            'uom_conversions',
            'vendors',
            'master_products',
            'master_product_uom_mappings',
            'master_product_channel_prices',
            'master_product_warehouse_stocks',
        ],
        'crm' => [
            'crm_customers',
            'crm_pipelines',
            'crm_leads',
            'crm_activities',
        ],
        'projects' => [
            'project_types',
            'projects',
            'project_payments',
            'project_budgets',
            'project_budget_items',
            'project_materials',
            'team_roles',
            'team_distributions',
            'project_tasks',
            'referrals',
        ],
        'purchasing' => [
            'procurement_import_stagings',
            'procurement_import_staging_lines',
            'purchase_orders',
            'purchase_order_lines',
            'goods_receipts',
            'goods_receipt_lines',
            'payables',
            'payable_payments',
        ],
        'sales' => [
            'pos_sales',
            'pos_sale_items',
            'pos_sale_additional_charges',
        ],
        'cashflow_accounting' => [
            'cash_in',
            'cash_out',
            'journal_entries',
            'journal_lines',
            'cash_bank_transfers',
            'accounting_inventory_records',
        ],
        'inventory_movements' => [
            'product_stock_movements',
        ],
        'hr' => [
            'employees',
            'payrolls',
        ],
        'rnd' => [
            'rnd_projects',
            'rnd_budget_items',
            'rnd_research_notes',
            'rnd_research_note_attachments',
            'rnd_purchases',
            'rnd_product_outputs',
        ],
    ],
];
