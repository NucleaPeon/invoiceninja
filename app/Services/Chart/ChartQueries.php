<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Chart;

use Illuminate\Support\Facades\DB;

/**
 * Class ChartQueries.
 */
trait ChartQueries
{
    /**
     * Expenses
     */
    public function getExpenseQuery($start_date, $end_date)
    {
        $user_filter = $this->is_admin ? '' : 'AND expenses.user_id = '.$this->user->id;

        return DB::select(DB::raw("
            SELECT sum(expenses.amount) as amount,
            IFNULL(expenses.currency_id, :company_currency) as currency_id
            FROM expenses
            WHERE expenses.is_deleted = 0
            AND expenses.company_id = :company_id
            AND (expenses.date BETWEEN :start_date AND :end_date)
            {$user_filter}
            GROUP BY currency_id
        "), ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]);
    }

    public function getExpenseChartQuery($start_date, $end_date, $currency_id)
    {

        $user_filter = $this->is_admin ? '' : 'AND expenses.user_id = '.$this->user->id;

        return DB::select(DB::raw("
            SELECT
            sum(expenses.amount) as total,
            expenses.date,
            IFNULL(expenses.currency_id, :company_currency) AS currency_id
            FROM expenses
            WHERE (expenses.date BETWEEN :start_date AND :end_date)
            AND expenses.company_id = :company_id
            AND expenses.is_deleted = 0
            {$user_filter}
            GROUP BY expenses.date
            HAVING currency_id = :currency_id
        "), [
            'company_currency' => $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * Payments
     */
    public function getPaymentQuery($start_date, $end_date)
    {
        
        $user_filter = $this->is_admin ? '' : 'AND payments.user_id = '.$this->user->id;

        return DB::select(DB::raw("
            SELECT sum(payments.amount) as amount,
            IFNULL(payments.currency_id, :company_currency) as currency_id
            FROM payments
            WHERE payments.is_deleted = 0
            {$user_filter}
            AND payments.company_id = :company_id
            AND (payments.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        "), [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    public function getPaymentChartQuery($start_date, $end_date, $currency_id)
    {

        $user_filter = $this->is_admin ? '' : 'AND payments.user_id = '.$this->user->id;

        return DB::select(DB::raw("
            SELECT
            sum(payments.amount - payments.refunded) as total,
            payments.date,
            IFNULL(payments.currency_id, :company_currency) AS currency_id
            FROM payments
            WHERE payments.company_id = :company_id
            AND payments.is_deleted = 0
            {$user_filter}
            AND payments.status_id IN (4,5,6)
            AND (payments.date BETWEEN :start_date AND :end_date)
            GROUP BY payments.date
            HAVING currency_id = :currency_id
        "), [
            'company_currency' => $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * Invoices
     */
    public function getOutstandingQuery($start_date, $end_date)
    {
        
        $user_filter = $this->is_admin ? '' : 'AND clients.user_id = '.$this->user->id;

        return DB::select(DB::raw("
            SELECT
            sum(invoices.balance) as amount,
            COUNT(*) as outstanding_count, 
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT( clients.settings, '$.currency_id' )) AS SIGNED), :company_currency) AS currency_id
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.status_id IN (2,3)
            AND invoices.company_id = :company_id
            AND clients.is_deleted = 0
            {$user_filter}
            AND invoices.is_deleted = 0
            AND invoices.balance > 0
            AND (invoices.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        "), ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]);
    }

    public function getRevenueQueryX($start_date, $end_date)
    {
        $user_filter = $this->is_admin ? '' : 'AND clients.user_id = '.$this->user->id;

        return DB::select(DB::raw("
            SELECT
            sum(invoices.paid_to_date) as paid_to_date,
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT( clients.settings, '$.currency_id' )) AS SIGNED), :company_currency) AS currency_id
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.company_id = :company_id
            AND clients.is_deleted = 0
            {$user_filter}
            AND invoices.is_deleted = 0
            AND invoices.amount > 0
            AND invoices.status_id IN (3,4)
            AND (invoices.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        "), ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]);
    }

    public function getRevenueQuery($start_date, $end_date)
    {
        $user_filter = $this->is_admin ? '' : 'AND payments.user_id = '.$this->user->id;

        return DB::select(DB::raw("
            SELECT
            sum(payments.amount - payments.refunded) as paid_to_date,
            payments.currency_id AS currency_id
            FROM payments
            WHERE payments.company_id = :company_id
            AND payments.is_deleted = 0
            {$user_filter}
            AND payments.status_id IN (1,4,5,6)
            AND (payments.date BETWEEN :start_date AND :end_date)
            GROUP BY payments.currency_id
        "), ['company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]);
    }

    public function getInvoicesQuery($start_date, $end_date)
    {
        $user_filter = $this->is_admin ? '' : 'AND clients.user_id = '.$this->user->id;

        return DB::select(DB::raw("
            SELECT
            sum(invoices.amount) as invoiced_amount,
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT( clients.settings, '$.currency_id' )) AS SIGNED), :company_currency) AS currency_id
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.status_id IN (2,3,4)
            AND invoices.company_id = :company_id
            {$user_filter}
            AND invoices.amount > 0
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            AND (invoices.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        "), ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]);
    }

    public function getOutstandingChartQuery($start_date, $end_date, $currency_id)
    {
        $user_filter = $this->is_admin ? '' : 'AND clients.user_id = '.$this->user->id;

        return DB::select(DB::raw("
            SELECT
            sum(invoices.balance) as total,
            invoices.date,
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT( clients.settings, '$.currency_id' )) AS SIGNED), :company_currency) AS currency_id
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.status_id IN (2,3,4)
            AND invoices.company_id = :company_id
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            {$user_filter}
            AND (invoices.date BETWEEN :start_date AND :end_date)
            GROUP BY invoices.date
            HAVING currency_id = :currency_id
        "), [
            'company_currency' => (int) $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }


    public function getInvoiceChartQuery($start_date, $end_date, $currency_id)
    {
        $user_filter = $this->is_admin ? '' : 'AND clients.user_id = '.$this->user->id;

        return DB::select(DB::raw("
            SELECT
            sum(invoices.amount) as total,
            invoices.date,
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT( clients.settings, '$.currency_id' )) AS SIGNED), :company_currency) AS currency_id
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.company_id = :company_id
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            {$user_filter}
            AND invoices.status_id IN (2,3,4)
            AND (invoices.date BETWEEN :start_date AND :end_date)
            GROUP BY invoices.date
            HAVING currency_id = :currency_id
        "), [
            'company_currency' => (int) $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }
}
