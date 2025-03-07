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

namespace App\Export\CSV;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\RecurringInvoice;
use App\Transformers\RecurringInvoiceTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class RecurringInvoiceExport extends BaseExport
{

    private $invoice_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    public array $entity_keys = [
        'amount' => 'amount',
        'balance' => 'balance',
        'client' => 'client_id',
        // 'custom_surcharge1' => 'custom_surcharge1',
        // 'custom_surcharge2' => 'custom_surcharge2',
        // 'custom_surcharge3' => 'custom_surcharge3',
        // 'custom_surcharge4' => 'custom_surcharge4',
        'custom_value1' => 'custom_value1',
        'custom_value2' => 'custom_value2',
        'custom_value3' => 'custom_value3',
        'custom_value4' => 'custom_value4',
        'date' => 'date',
        'discount' => 'discount',
        'due_date' => 'due_date',
        'exchange_rate' => 'exchange_rate',
        'footer' => 'footer',
        'number' => 'number',
        'paid_to_date' => 'paid_to_date',
        'partial' => 'partial',
        'partial_due_date' => 'partial_due_date',
        'po_number' => 'po_number',
        'private_notes' => 'private_notes',
        'public_notes' => 'public_notes',
        'next_send_date' => 'next_send_date',
        'status' => 'status_id',
        'tax_name1' => 'tax_name1',
        'tax_name2' => 'tax_name2',
        'tax_name3' => 'tax_name3',
        'tax_rate1' => 'tax_rate1',
        'tax_rate2' => 'tax_rate2',
        'tax_rate3' => 'tax_rate3',
        'terms' => 'terms',
        'total_taxes' => 'total_taxes',
        'currency' => 'currency_id',
        'vendor' => 'vendor_id',
        'project' => 'project_id',
        'frequency_id' => 'frequency_id',
    ];

    private array $decorate_keys = [
        'country',
        'client',
        'currency',
        'status',
        'vendor',
        'project',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->invoice_transformer = new RecurringInvoiceTransformer();
    }

    public function run()
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->entity_keys);
        }

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query = RecurringInvoice::query()
                        ->withTrashed()
                        ->with('client')->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        $query->cursor()
            ->each(function ($invoice) {
                $this->csv->insertOne($this->buildRow($invoice));
            });

        return $this->csv->toString();
    }

    private function buildRow(RecurringInvoice $invoice) :array
    {
        $transformed_invoice = $this->invoice_transformer->transform($invoice);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->entity_keys);

            if(!$keyval) {
                $keyval = array_search(str_replace("recurring_invoice.", "", $key), $this->entity_keys) ?? $key;
            }

            if(!$keyval) {
                $keyval = $key;
            }

            if (array_key_exists($key, $transformed_invoice)) {
                $entity[$keyval] = $transformed_invoice[$key];
            } elseif (array_key_exists($keyval, $transformed_invoice)) {
                $entity[$keyval] = $transformed_invoice[$keyval];
            } else {
                $entity[$keyval] = $this->resolveKey($keyval, $invoice, $this->invoice_transformer);
            }

        }

        return $this->decorateAdvancedFields($invoice, $entity);
    }

    private function decorateAdvancedFields(RecurringInvoice $invoice, array $entity) :array
    {
        if (in_array('country_id', $this->input['report_keys'])) {
            $entity['country'] = $invoice->client->country ? ctrans("texts.country_{$invoice->client->country->name}") : '';
        }

        if (in_array('currency_id', $this->input['report_keys'])) {
            $entity['currency'] = $invoice->client->currency() ? $invoice->client->currency()->code : $invoice->company->currency()->code;
        }

        if (in_array('client_id', $this->input['report_keys'])) {
            $entity['client'] = $invoice->client->present()->name();
        }

        if (in_array('status_id', $this->input['report_keys'])) {
            $entity['status'] = $invoice->stringStatus($invoice->status_id);
        }

        if (in_array('project_id', $this->input['report_keys'])) {
            $entity['project'] = $invoice->project ? $invoice->project->name : '';
        }

        if (in_array('vendor_id', $this->input['report_keys'])) {
            $entity['vendor'] = $invoice->vendor ? $invoice->vendor->name : '';
        }

        if (in_array('recurring_invoice.frequency_id', $this->input['report_keys']) || in_array('frequency_id', $this->input['report_keys'])) {
            $entity['frequency_id'] = $invoice->frequencyForKey($invoice->frequency_id);
        }

        return $entity;
    }
}
