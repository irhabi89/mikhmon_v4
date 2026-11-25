<?php
/**
 * Per-server dashboard cards.
 * @var array<int,array<string,mixed>> $rows
 * @var callable $formatMoney
 * @var string $session
 */
?>
<div class="card" style="margin-top:10px;">
  <div class="card-header"><h3><i class="fa fa-server"></i> Per Server</h3></div>
  <div class="card-body">
    <div class="row" style="display:flex;flex-wrap:wrap;">
      <?php foreach ($rows as $srv): ?>
        <?php $isNone = $srv['name'] === '__none__'; $srvName = $isNone ? 'Tanpa server (arsip)' : $srv['name']; ?>
        <div class="col-3 col-box-6" style="display:flex;flex-grow:1;">
          <div class="box bg-blue bmh-75" style="width:100%;">
            <a onclick="cancelPage()" href="./?report=selling&session=<?= urlencode((string)$session); ?>&server=<?= urlencode($isNone ? '__none__' : (string)$srv['name']); ?>" style="text-decoration:none;color:inherit;">
              <div style="font-size:12px;font-weight:bold;margin-bottom:4px;"><i class="fa fa-server"></i> <?= htmlspecialchars((string)$srvName, ENT_QUOTES, 'UTF-8'); ?></div>
              <h1 style="margin:0;font-size:24px;"><?= number_format((int)$srv['active'], 0, ',', '.'); ?> <span style="font-size:12px;">active</span></h1>
              <div style="font-size:12px;"><?= number_format((int)$srv['users'], 0, ',', '.'); ?> user</div>
              <div style="font-size:12px;margin-top:4px;">Today: <b><?= htmlspecialchars($formatMoney($srv['today_sum']), ENT_QUOTES, 'UTF-8'); ?></b> (<?= number_format((int)$srv['today_count'], 0, ',', '.'); ?>)</div>
              <div style="font-size:12px;">Month: <b><?= htmlspecialchars($formatMoney($srv['month_sum']), ENT_QUOTES, 'UTF-8'); ?></b> (<?= number_format((int)$srv['month_count'], 0, ',', '.'); ?>)</div>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?><div class="col-12 pd-10">-</div><?php endif; ?>
    </div>
  </div>
</div>
