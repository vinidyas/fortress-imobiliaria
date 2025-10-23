<?php

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Resources\Financeiro\JournalEntryResource;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Models\CostCenter;
use App\Models\FinancialAccount;
use App\Models\Imovel;
use App\Models\JournalEntry;
use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceiroPageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        $query = $this->makeFilteredQuery($request);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $transactions = $query
            ->orderByDesc('movement_date')
            ->paginate($perPage)
            ->withQueryString();

        $totals = $this->calculateTotals($this->makeFilteredQuery($request, false));

        return Inertia::render('Financeiro/Index', [
            'entries' => JournalEntryResource::collection($transactions),
            'accounts' => FinancialAccount::query()->orderBy('nome')->get(['id', 'nome']),
            'costCenters' => CostCenter::query()->orderBy('nome')->get(['id', 'nome', 'codigo', 'parent_id']),
            'people' => Pessoa::query()
                ->orderBy('nome_razao_social')
                ->get([
                    'id',
                    'nome_razao_social as nome',
                    'papeis',
                ]),
            'properties' => Imovel::query()
                ->with('condominio:id,nome')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'complemento', 'condominio_id'])
                ->map(function (Imovel $imovel) {
                    $condominioNome = trim($imovel->condominio->nome ?? '');
                    $complemento = trim($imovel->complemento ?? '');
                    $titulo = $condominioNome;

                    if ($complemento !== '') {
                        $titulo = $titulo !== ''
                            ? sprintf('%s — %s', $condominioNome, $complemento)
                            : $complemento;
                    }

                    if ($titulo === '') {
                        $titulo = sprintf('Imóvel %s', $imovel->codigo ?? $imovel->id);
                    }

                    return [
                        'id' => $imovel->id,
                        'titulo' => $titulo,
                        'codigo_interno' => $imovel->codigo,
                    ];
                })
                ->values(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'account_id' => $request->input('filter.account_id'),
                'cost_center_id' => $request->input('filter.cost_center_id'),
                'status' => $request->input('filter.status'),
                'tipo' => $request->input('filter.tipo'),
                'data_de' => $request->input('filter.data_de'),
                'data_ate' => $request->input('filter.data_ate'),
            ],
            'totals' => $totals,
            'can' => [
                'create' => $request->user()->can('create', JournalEntry::class),
                'reconcile' => $request->user()->hasPermission('financeiro.reconcile'),
                'export' => $request->user()->can('export', JournalEntry::class),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
            ],
            'permissions' => [
                'update' => $request->user()->hasPermission('financeiro.update'),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
                'reconcile' => $request->user()->hasPermission('financeiro.reconcile'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', JournalEntry::class);

        return Inertia::render('Financeiro/Transactions/Form', [
            'mode' => 'create',
            'transaction' => null,
            'accounts' => FinancialAccount::query()->orderBy('nome')->get(['id', 'nome']),
            'costCenters' => CostCenter::query()->orderBy('nome')->get(['id', 'nome', 'codigo', 'parent_id']),
            'people' => Pessoa::query()
                ->orderBy('nome_razao_social')
                ->get([
                    'id',
                    'nome_razao_social as nome',
                    'papeis',
                ]),
            'properties' => Imovel::query()
                ->with('condominio:id,nome')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'complemento', 'condominio_id'])
                ->map(function (Imovel $imovel) {
                    $condominioNome = trim($imovel->condominio->nome ?? '');
                    $complemento = trim($imovel->complemento ?? '');
                    $titulo = $condominioNome;

                    if ($complemento !== '') {
                        $titulo = $titulo !== ''
                            ? sprintf('%s — %s', $condominioNome, $complemento)
                            : $complemento;
                    }

                    if ($titulo === '') {
                        $titulo = sprintf('Imóvel %s', $imovel->codigo ?? $imovel->id);
                    }

                    return [
                        'id' => $imovel->id,
                        'titulo' => $titulo,
                        'codigo_interno' => $imovel->codigo,
                    ];
                })
                ->values(),
            'permissions' => [
                'update' => $request->user()->hasPermission('financeiro.update'),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
                'reconcile' => $request->user()->hasPermission('financeiro.reconcile'),
            ],
        ]);
    }

    public function edit(Request $request, JournalEntry $journalEntry): Response
    {
        $this->authorize('view', $journalEntry);

        $journalEntry->load(['bankAccount', 'counterBankAccount', 'costCenter.parent', 'person', 'installments', 'allocations']);

        return Inertia::render('Financeiro/Transactions/Form', [
            'mode' => 'edit',
            'transaction' => JournalEntryResource::make(
                $journalEntry->load([
                    'bankAccount',
                    'counterBankAccount',
                    'costCenter.parent',
                    'person',
                    'installments',
                    'allocations',
                    'attachments.uploadedBy',
                    'receipts.issuedBy',
                ])
            ),
            'accounts' => FinancialAccount::query()->orderBy('nome')->get(['id', 'nome']),
            'costCenters' => CostCenter::query()->orderBy('nome')->get(['id', 'nome', 'codigo', 'parent_id']),
            'people' => Pessoa::query()
                ->orderBy('nome_razao_social')
                ->get([
                    'id',
                    'nome_razao_social as nome',
                    'papeis',
                ]),
            'properties' => Imovel::query()
                ->with('condominio:id,nome')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'complemento', 'condominio_id'])
                ->map(function (Imovel $imovel) {
                    $condominioNome = trim($imovel->condominio->nome ?? '');
                    $complemento = trim($imovel->complemento ?? '');
                    $titulo = $condominioNome;

                    if ($complemento !== '') {
                        $titulo = $titulo !== ''
                            ? sprintf('%s — %s', $condominioNome, $complemento)
                            : $complemento;
                    }

                    if ($titulo === '') {
                        $titulo = sprintf('Imóvel %s', $imovel->codigo ?? $imovel->id);
                    }

                    return [
                        'id' => $imovel->id,
                        'titulo' => $titulo,
                        'codigo_interno' => $imovel->codigo,
                    ];
                })
                ->values(),
            'permissions' => [
                'update' => $request->user()->hasPermission('financeiro.update'),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
                'reconcile' => $request->user()->hasPermission('financeiro.reconcile'),
            ],
        ]);
    }

    private function makeFilteredQuery(Request $request, bool $withRelations = true): Builder
    {
        $query = JournalEntry::query();

        if ($withRelations) {
            $query->with(['bankAccount', 'costCenter.parent', 'person']);
        }

        return $query
            ->when($request->filled('filter.search'), function ($q) use ($request) {
                $search = (string) $request->string('filter.search');
                $term = '%'.str_replace('%', '', $search).'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('description_custom', 'like', $term)
                        ->orWhere('notes', 'like', $term);
                });
            })
            ->when($request->filled('filter.tipo'), fn ($q) => $q->where('type', $request->string('filter.tipo')))
            ->when($request->filled('filter.status'), function ($q) use ($request) {
                $statuses = JournalEntryStatus::filterValues((string) $request->string('filter.status'));

                return count($statuses) === 1
                    ? $q->where('status', $statuses[0])
                    : $q->whereIn('status', $statuses);
            })
            ->when($request->filled('filter.account_id'), fn ($q) => $q->where('bank_account_id', $request->integer('filter.account_id')))
            ->when($request->filled('filter.cost_center_id'), fn ($q) => $q->where('cost_center_id', $request->integer('filter.cost_center_id')))
            ->when($request->filled('filter.data_de'), fn ($q) => $q->whereDate('movement_date', '>=', $request->date('filter.data_de')->toDateString()))
            ->when($request->filled('filter.data_ate'), fn ($q) => $q->whereDate('movement_date', '<=', $request->date('filter.data_ate')->toDateString()));
    }

    private function calculateTotals(Builder $builder): array
    {
        $receita = (float) (clone $builder)->where('type', 'receita')->sum('amount');
        $despesa = (float) (clone $builder)->where('type', 'despesa')->sum('amount');

        return [
            'receita' => $receita,
            'despesa' => $despesa,
            'saldo' => $receita - $despesa,
        ];
    }
}
