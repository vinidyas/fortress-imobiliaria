<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\UpdateFinancialAccountBalanceRequest;
use App\Http\Resources\Financeiro\FinancialAccountResource;
use App\Models\FinancialAccount;
use Illuminate\Http\JsonResponse;

class FinancialAccountBalanceController extends Controller
{
    public function update(UpdateFinancialAccountBalanceRequest $request, FinancialAccount $account): JsonResponse
    {
        $account->update($request->validated());

        return response()->json([
            'message' => 'Saldo inicial atualizado com sucesso.',
            'account' => FinancialAccountResource::make($account)->resolve(),
        ]);
    }
}
