<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportBankLedgerFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('reports.view.financeiro');
    }

    public function rules(): array
    {
        return [
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', Rule::in(['receita', 'despesa', 'transferencia'])],
            'status' => ['nullable', 'string', Rule::in([
                'planejado',
                'pendente',
                'pago',
                'cancelado',
                'atrasado',
                'open',
                'settled',
                'cancelled',
                'overdue',
            ])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
            'format' => ['nullable', Rule::in(['csv', 'pdf'])],
            'preview' => ['sometimes', 'boolean'],
        ];
    }
}
