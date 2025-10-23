<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <title>Relatório de Despesas Detalhado</title>
    <style>
      @page {
        margin: 18mm 8mm 16mm 8mm;
      }
      :root {
        color-scheme: light;
      }
      body {
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        font-size: 12px;
        color: #1f2937;
        margin: 0;
        padding: 0;
        line-height: 1.4;
      }
      .wrapper {
        padding: 10px 6px;
      }
      header {
        margin-bottom: 8px;
      }
      .header-table {
        width: 100%;
        border-collapse: collapse;
      }
      .header-logo {
        width: 170px;
        vertical-align: middle;
      }
      .logo {
        width: 160px;
        height: auto;
        display: block;
      }
      .company-info {
        text-align: right;
        vertical-align: middle;
        padding-left: 12px;
      }
      .company-info h1 {
        font-size: 18px;
        margin: 0;
        font-weight: 700;
        color: #111827;
      }
      .company-info p {
        margin: 4px 0 0;
        color: #6b7280;
      }
      h2 {
        font-size: 16px;
        margin: 8px 0 12px;
        color: #111827;
      }
      .section-divider {
        height: 1px;
        background-color: #d1d5db;
        margin-bottom: 12px;
      }
      table {
        width: 100%;
        border-collapse: collapse;
      }
      th {
        background-color: #111827;
        color: #f9fafb;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 11px;
        padding: 8px 10px;
        text-align: left;
      }
      td {
        padding: 8px 10px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
      }
      .text-right {
        text-align: right;
      }
      .muted {
        color: #6b7280;
        font-size: 11px;
      }
      section {
        margin-bottom: 18px;
      }
      .report-title {
        font-size: 20px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        text-align: center;
        margin: 0 0 16px;
        color: #111827;
      }
      .summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 16px;
      }
      .summary-card {
        border: 1px solid #d1d5db;
        border-radius: 10px;
        padding: 10px 12px;
        background-color: #f9fafb;
      }
      .summary-card-inline {
        padding: 9px 12px;
      }
      .summary-inline {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12.5px;
      }
      .summary-inline--wrap {
        flex-wrap: wrap;
        gap: 6px;
      }
      .summary-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
      }
      .summary-value {
        font-size: 13px;
        font-weight: 600;
        color: #111827;
      }
      .summary-grow {
        flex: 1 1 auto;
      }
      .summary-divider {
        color: #9ca3af;
        font-size: 12px;
      }
      .footer {
        margin-top: 32px;
        font-size: 10px;
        color: #9ca3af;
        text-align: right;
      }
      .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 9999px;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
      }
      .badge-open { background-color: #fef3c7; color: #92400e; }
      .badge-settled { background-color: #dcfce7; color: #166534; }
      .badge-cancelled { background-color: #ffe4e6; color: #b91c1c; }
      .badge-overdue { background-color: #fde68a; color: #92400e; }
      .column-date,
      .column-date-cell {
        width: 1%;
        text-align: left;
        white-space: nowrap;
        padding-left: 4px !important;
        padding-right: 4px !important;
      }
      .column-due,
      .column-due-cell {
        width: 1%;
        text-align: left;
        white-space: nowrap;
        padding-left: 4px !important;
        padding-right: 4px !important;
      }
      .column-supplier { width: 24%; }
      .column-description { width: 32%; }
      .column-property { width: 18%; }
      .column-cost-center { width: 12%; }
      .column-status { width: 12%; }
      .column-value {
        width: 14%;
        white-space: nowrap;
        padding-left: 6px !important;
        padding-right: 6px !important;
        font-variant-numeric: tabular-nums;
      }
    </style>
  </head>
  <body>
    <div class="wrapper">
      <header>
        <table class="header-table">
          <tr>
            <td class="header-logo">
              <?php if($logoBase64): ?>
                <img src="<?php echo e($logoBase64); ?>" alt="Logo <?php echo e($company['name'] ?? config('app.name')); ?>" class="logo">
              <?php endif; ?>
            </td>
            <td class="company-info">
              <h1>FORTRESS EMPREENDIMENTOS</h1>
              <p>Relatório de Despesas Detalhado</p>
              <p class="muted">
                Gerado em <?php echo e(optional($filters['generated_at'] ?? null)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i')); ?>

              </p>
            </td>
          </tr>
        </table>
      </header>

      <div class="section-divider"></div>

      <h1 class="report-title">Relatório de Despesas</h1>

      <section>
        <h2>Despesas</h2>
        <table>
          <thead>
            <tr>
              <th class="column-date">Data</th>
              <th class="column-supplier">Fornecedor</th>
              <th class="column-description">Descrição</th>
              <th class="column-property">Imóvel</th>
              <th class="column-cost-center">C.C</th>
              <th class="column-due">Venc.</th>
              <th class="column-status">Status</th>
              <th class="column-value text-right">Valor</th>
            </tr>
          </thead>
          <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
              <tr>
                <td class="column-date-cell">
                  <?php echo e($row['movement_date'] ? \Illuminate\Support\Carbon::parse($row['movement_date'])->format('d/m/Y') : '-'); ?>

                </td>
                <td class="column-supplier">
                  <?php echo e(!empty($row['person']['nome']) ? mb_strtoupper($row['person']['nome'], 'UTF-8') : '—'); ?>

                </td>
                <td class="column-description">
                  <strong><?php echo e(mb_strtoupper($row['description'] ?? 'Sem descrição', 'UTF-8')); ?></strong>
                </td>
                <td class="column-property">
                  <?php if(!empty($row['property']['nome'])): ?>
                    <?php echo e(mb_strtoupper($row['property']['nome'], 'UTF-8')); ?>

                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
                <td class="column-cost-center">
                  <?php if(!empty($row['cost_center']['nome'])): ?>
                    <strong><?php echo e($row['cost_center']['nome']); ?></strong>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
                <td class="column-due-cell">
                  <?php echo e($row['due_date'] ? \Illuminate\Support\Carbon::parse($row['due_date'])->format('d/m/Y') : '—'); ?>

                </td>
                <td class="column-status">
                  <?php echo e($row['status_label'] ?? '—'); ?>

                </td>
                <td class="column-value text-right" style="font-weight:600;">
                  R$ <?php echo e(number_format($row['signed_amount'] ?? 0, 2, ',', '.')); ?>

                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
              <tr>
                <td colspan="7" class="text-right muted">Nenhuma movimentação encontrada no período.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <div class="footer">
        Documento emitido em <?php echo e(optional($filters['generated_at'] ?? null)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i')); ?> por <?php echo e($filters['generated_by'] ?? 'Usuário não identificado'); ?> · Uso interno
      </div>
    </div>
  </body>
</html>
<?php /**PATH /home/vinidyas/projetos/fortress-laravel/resources/views/pdf/bank-ledger-report.blade.php ENDPATH**/ ?>