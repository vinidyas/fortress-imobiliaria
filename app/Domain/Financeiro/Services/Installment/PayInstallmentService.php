<?php

namespace App\Domain\Financeiro\Services\Installment;

use App\Domain\Financeiro\Services\JournalEntryStateService;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Models\JournalEntryInstallment;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

class PayInstallmentService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly JournalEntryStateService $stateService,
    ) {
    }

    public function handle(JournalEntryInstallment $installment, string $paymentDate, ?float $penalty = null, ?float $interest = null, ?float $discount = null): JournalEntryInstallment
    {
        $installment->loadMissing('journalEntry');
        $entry = $installment->journalEntry;

        if ($installment->status === JournalEntryStatus::Pago->value) {
            throw new InvalidArgumentException('Parcela já está quitada.');
        }

        if ($entry->status === JournalEntryStatus::Cancelado->value) {
            throw new InvalidArgumentException('Não é possível pagar parcela de lançamento cancelado.');
        }

        return $this->database->transaction(function () use ($installment, $entry, $paymentDate, $penalty, $interest, $discount) {
            $date = CarbonImmutable::parse($paymentDate)->toDateString();
            $previousStatus = JournalEntryStatus::from($entry->status);

            $installment->update([
                'payment_date' => $date,
                'valor_multa' => $penalty ?? $installment->valor_multa,
                'valor_juros' => $interest ?? $installment->valor_juros,
                'valor_desconto' => $discount ?? $installment->valor_desconto,
                'status' => JournalEntryStatus::Pago->value,
            ]);

            $this->stateService->sync($entry->refresh(), $previousStatus);

            return $installment->fresh();
        });
    }
}
