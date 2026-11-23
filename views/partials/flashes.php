<?php
$messages = flashes();
if ($messages === []) {
    return;
}
?>
<div class="flashes" role="status" aria-live="polite">
  <?php foreach ($messages as $message): ?>
    <div class="flash <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
  <?php endforeach; ?>
</div>
