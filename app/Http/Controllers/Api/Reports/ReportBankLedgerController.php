<?php

namespace App\Http\Controllers\Api\Reports;

use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Support\JournalEntryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportBankLedgerFilterRequest;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportBankLedgerController extends Controller
{
    public function index(ReportBankLedgerFilterRequest $request): JsonResponse
    {
        $hasSpecificAccount = $request->filled('financial_account_id');
        $account = null;

        if ($hasSpecificAccount) {
            $accountId = (int) $request->integer('financial_account_id');
            $account = FinancialAccount::query()->findOrFail($accountId);
        }

        $dateFrom = $request->filled('date_from') ? $request->date('date_from')->toDateString() : null;
        $dateTo = $request->filled('date_to') ? $request->date('date_to')->toDateString() : null;

        $openingBalance = $this->calculateOpeningBalance($request);

        $relations = ['costCenter', 'person', 'property'];

        if (Schema::hasTable('journal_entry_installments')) {
            $relations['installments'] = fn ($query) => $query
                ->select('id', 'journal_entry_id', 'meta')
                ->orderBy('id');
        }

        $entries = $this->baseQuery($request)
            ->with($relations)
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        $rows = collect($this->buildRows($entries, $openingBalance, true));

        $totalsIn = $rows->sum('amount_in');
        $totalsOut = $rows->sum('amount_out');
        $closingBalance = $rows->last()['balance_after'] ?? round($openingBalance, 2);

        return response()->json([
            'account' => [
                'id' => $account?->id,
                'nome' => $account?->nome ?? 'Todos os bancos',
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => $closingBalance,
            'totals' => [
                'inflow' => round($totalsIn, 2),
                'outflow' => round($totalsOut, 2),
                'net' => round($totalsIn - $totalsOut, 2),
            ],
            'data' => $rows,
        ]);
    }

    public function export(ReportBankLedgerFilterRequest $request): StreamedResponse|Response
    {
        if (! $request->user()?->hasPermission('reports.export')) {
            abort(403);
        }

        $format = strtolower((string) $request->input('format', 'csv'));
        if (! in_array($format, ['csv', 'pdf'], true)) {
            abort(422, 'Formato solicitado nao e suportado.');
        }

        $account = $request->filled('financial_account_id')
            ? FinancialAccount::query()->findOrFail((int) $request->integer('financial_account_id'))
            : null;
        $accountName = $account?->nome ?? 'Todos os bancos';

        $openingBalance = $this->calculateOpeningBalance($request);
        $exportRelations = ['costCenter', 'person', 'property'];

        if (Schema::hasTable('journal_entry_installments')) {
            $exportRelations['installments'] = fn ($query) => $query
                ->select('id', 'journal_entry_id', 'meta')
                ->orderBy('id');
        }

        $entries = $this->baseQuery($request)
            ->with($exportRelations)
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        $rows = $this->buildRows($entries, $openingBalance, true);

        if ($format === 'pdf') {
            return $this->downloadPdf($accountName, $request, $openingBalance, $rows);
        }

        $filename = 'extrato-detalhado-'.Str::slug($accountName).'-'.now()->format('Ymd_His').'.csv';

        $totalAbsolute = array_reduce($rows, function ($carry, $row) {
            $absolute = $row['absolute_amount'] ?? null;
            $signed = $row['signed_amount'] ?? 0;
            $value = $absolute !== null ? $absolute : abs($signed);

            return $carry + $value;
        }, 0.0);

        $totalRevenue = array_reduce($rows, function ($carry, $row) {
            $absolute = $row['absolute_amount'] ?? null;
            $signed = $row['signed_amount'] ?? 0;
            $value = $signed >= 0 ? ($absolute !== null ? $absolute : $signed) : 0;

            return $carry + $value;
        }, 0.0);

        $isExpenseReport = $request->input('type', 'despesa') === 'despesa';

        return response()->streamDownload(function () use ($rows, $accountName, $request, $openingBalance, $isExpenseReport, $totalAbsolute) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Conta', $accountName]);
            fputcsv($handle, ['Período', ($request->input('date_from') ?? 'Início').' a '.($request->input('date_to') ?? 'Hoje')]);
            fputcsv($handle, ['Saldo inicial', number_format($openingBalance, 2, '.', '')]);
            fputcsv($handle, []);

            fputcsv($handle, [
                'Data',
                'Fornecedor',
                'Descrição',
                'Imóvel',
                'Vencimento',
                'Status',
                'Valor',
            ]);

            foreach ($rows as $row) {
                $propertyName = $row['property']['nome'] ?? '';

                $signedAmount = $row['signed_amount'] ?? 0;
                $absoluteAmount = $row['absolute_amount'] ?? null;
                $value = $isExpenseReport
                    ? ($absoluteAmount !== null ? $absoluteAmount : abs($signedAmount))
                    : $signedAmount;

                fputcsv($handle, [
                    $row['movement_date'],
                    $row['person']['nome'] ?? '',
                    $row['description'],
                    $propertyName,
                    $row['due_date'],
                    $row['status_label'],
                    number_format($value, 2, '.', ''),
                ]);
            }

            if ($isExpenseReport) {
                fputcsv($handle, []);
                fputcsv($handle, ['TOTAL DAS DESPESAS', null, null, null, null, null, number_format($totalAbsolute, 2, '.', '')]);
            } else {
                fputcsv($handle, []);
                fputcsv($handle, ['TOTAL DE RECEITAS', null, null, null, null, null, number_format($totalRevenue, 2, '.', '')]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Builder<JournalEntry>
     */
    private function baseQuery(ReportBankLedgerFilterRequest $request): Builder
    {
        $query = JournalEntry::query();

        if ($request->filled('financial_account_id')) {
            $query->where('bank_account_id', $request->integer('financial_account_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->string('type'));
        }

        if ($request->filled('status')) {
            $statuses = JournalEntryStatus::filterValues((string) $request->string('status'));

            $query->when(
                count($statuses) === 1,
                fn ($builder) => $builder->where('status', $statuses[0]),
                fn ($builder) => $builder->whereIn('status', $statuses)
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate('movement_date', '>=', $request->date('date_from')->toDateString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('movement_date', '<=', $request->date('date_to')->toDateString());
        }

        return $query;
    }

    private function calculateOpeningBalance(ReportBankLedgerFilterRequest $request): float
    {
        if (! $request->filled('date_from')) {
            return 0.0;
        }

        $query = JournalEntry::query()
            ->when(
                $request->filled('financial_account_id'),
                fn (Builder $builder) => $builder->where('bank_account_id', $request->integer('financial_account_id'))
            )
            ->whereDate('movement_date', '<', $request->date('date_from')->toDateString());

        if ($request->filled('status')) {
            $statuses = JournalEntryStatus::filterValues((string) $request->string('status'));
            $query->when(
                count($statuses) === 1,
                fn ($builder) => $builder->where('status', $statuses[0]),
                fn ($builder) => $builder->whereIn('status', $statuses)
            );
        }

        return (float) $query->get()->sum(fn (JournalEntry $entry) => $this->resolveSignedAmount($entry));
    }

    private function resolveSignedAmount(JournalEntry $entry): float
    {
        $amount = (float) $entry->amount;

        return match ($entry->type) {
            'receita' => $amount,
            'despesa' => -$amount,
            'transferencia' => -$amount,
            default => 0.0,
        };
    }

    /**
     * @param  Collection<int,JournalEntry>  $entries
     * @return array<int,array<string,mixed>>
     */
    private function buildRows(Collection $entries, float $openingBalance, bool $detailed = false): array
    {
        $running = $openingBalance;

        return $entries->map(function (JournalEntry $entry) use (&$running, $detailed) {
            $typeEnum = $entry->type ? JournalEntryType::tryFrom((string) $entry->type) : null;
            $statusEnum = $entry->status ? JournalEntryStatus::tryFrom((string) $entry->status) : null;
            $signed = $this->resolveSignedAmount($entry);
            $running += $signed;

            $propertyLabel = $this->resolveEntryPropertyLabel($entry);

            $base = [
                'id' => $entry->id,
                'movement_date' => $entry->movement_date?->toDateString(),
                'due_date' => $entry->due_date?->toDateString(),
                'description' => $entry->description_custom ?? $entry->description_id,
                'type' => $entry->type,
                'type_label' => $typeEnum ? ucfirst($typeEnum->name) : ucfirst((string) $entry->type),
                'property' => $propertyLabel
                    ? [
                        'id' => $entry->property?->id,
                        'nome' => $propertyLabel,
                    ]
                    : null,
                'cost_center' => $entry->costCenter
                    ? [
                        'id' => $entry->costCenter->id,
                        'nome' => $entry->costCenter->nome,
                        'codigo' => $entry->costCenter->codigo,
                    ]
                    : null,
                'amount_in' => $signed > 0 ? round($signed, 2) : 0.0,
                'amount_out' => $signed < 0 ? round(abs($signed), 2) : 0.0,
                'balance_after' => round($running, 2),
                'status_label' => $statusEnum
                    ? $statusEnum->label($typeEnum)
                    : ($entry->status ? ucfirst($entry->status) : null),
                'status_category' => $statusEnum?->category(),
            ];

            if ($detailed) {
                $base['notes'] = $entry->notes;
                $base['reference_code'] = $entry->reference_code;
                $base['amount'] = (float) $entry->amount;
                $base['person'] = $entry->person
                    ? [
                        'id' => $entry->person->id,
                        'nome' => $entry->person->nome,
                    ]
                    : null;
                $base['property'] = $propertyLabel
                    ? [
                        'id' => $entry->property?->id,
                        'nome' => $propertyLabel,
                    ]
                    : null;
                $base['signed_amount'] = round($signed, 2);
                $base['absolute_amount'] = round(abs($signed), 2);
                $base['status'] = $entry->status;
            }

            return $base;
        })->toArray();
    }

    private function downloadPdf(string $accountName, ReportBankLedgerFilterRequest $request, float $openingBalance, array $rows): Response
    {
        $closingBalance = ! empty($rows) ? end($rows)['balance_after'] : $openingBalance;
        $totals = [
            'inflow' => round(array_sum(array_column($rows, 'amount_in')), 2),
            'outflow' => round(array_sum(array_column($rows, 'amount_out')), 2),
            'net' => round(array_sum(array_column($rows, 'amount_in')) - array_sum(array_column($rows, 'amount_out')), 2),
        ];

        $logoPath = base_path('docs/identidade-visual-fortress_3.jpg');
        $logoBase64 = File::exists($logoPath)
            ? 'data:image/jpeg;base64,'.base64_encode(File::get($logoPath))
            : null;

        $user = $request->user();
        $generatedBy = $user?->nome ?? $user?->name ?? $user?->username;

        $data = [
            'account' => ['nome' => $accountName],
            'filters' => [
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'type' => $request->input('type', 'despesa'),
                'generated_at' => now(),
                'generated_by' => $generatedBy,
            ],
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'totals' => $totals,
            'rows' => $rows,
            'company' => [
                'name' => config('app.company_name', config('app.name', 'Fortress Empreendimentos')),
            ],
            'logoBase64' => $logoBase64,
        ];

        $filename = sprintf(
            'relatorio-despesas-%s-%s.pdf',
            Str::slug($accountName),
            now()->format('Ymd_His')
        );

        $html = view('pdf.bank-ledger-report', $data)->render();

        if ($request->boolean('preview')) {
            return response($html);
        }

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function resolvePropertyLabel($property): ?string
    {
        if (! $property) {
            return null;
        }

        $segments = [];

        if (! empty($property->complemento)) {
            $segments[] = trim((string) $property->complemento);
        }

        if (! empty($property->logradouro)) {
            $logradouro = trim((string) $property->logradouro);
            if (! empty($property->numero)) {
                $logradouro = trim($logradouro.' '.$property->numero);
            }
            $segments[] = $logradouro;
        }

        if (! empty($property->bairro)) {
            $segments[] = trim((string) $property->bairro);
        }

        if (! empty($property->cidade)) {
            $segments[] = trim((string) $property->cidade);
        }

        if (empty($segments) && ! empty($property->codigo)) {
            $segments[] = trim((string) $property->codigo);
        }

        $label = trim(implode(' • ', array_filter($segments)));

        return $label !== '' ? $label : ($property->codigo ?? null);
    }

    private function resolveEntryPropertyLabel(JournalEntry $entry): ?string
    {
        $label = null;

        if ($entry->relationLoaded('property') && $entry->property) {
            $label = $this->resolvePropertyLabel($entry->property);
        }

        if (! $label) {
            $label = $entry->costCenter?->nome;
        }

        if (! $label && Schema::hasTable('journal_entry_installments')) {
            if ($entry->relationLoaded('installments')) {
                $firstInstallment = $entry->installments->first();
            } else {
                $firstInstallment = $entry->installments()
                    ->select('meta')
                    ->orderBy('id')
                    ->first();
            }

            if ($firstInstallment && is_array($firstInstallment->meta ?? null)) {
                $label = $firstInstallment->meta['property_label'] ?? null;
            }
        }

        return $label;
    }
}
