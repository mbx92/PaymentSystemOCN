<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ErpChatParserRule extends Model
{
    private const VALID_INTENT_KEYS = [
        'greeting', 'stock_lookup', 'product_price_lookup', 'product_detail',
        'low_stock_alert', 'top_selling_products',
        'invoice_unpaid_list', 'invoice_due_list',
        'pos_sales_today', 'pos_sales_yesterday', 'pos_sales_month', 'pos_sales_last_month',
        'cashflow_today', 'cashflow_yesterday', 'cashflow_month', 'cashflow_last_month',
        'project_active_list', 'operational_summary',
        'send_invoice', 'invoice_sent_list',
        'help',
    ];

    protected $fillable = [
        'name',
        'intent_key',
        'keywords',
        'match_mode',
        'priority',
        'is_active',
        'notes',
        'response_text',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $rule): void {
            if ($rule->intent_key && ! in_array($rule->intent_key, self::VALID_INTENT_KEYS, true)) {
                $rule->intent_key = '';
            }
        });

        static::saved(function (): void {
            Cache::forget('erp_chat_parser_rules');
        });

        static::deleted(function (): void {
            Cache::forget('erp_chat_parser_rules');
        });
    }
}
