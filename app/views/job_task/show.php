<h4><?= $this->t(['.title', 'id' => $this->job_task->id]) ?></h4>

<ul>
  <li><strong><?= $this->t('.table.type') ?></strong>: <?= $this->job_task->task_type ?></li>
  <li><strong><?= $this->t('.table.status') ?></strong>: <?= $this->job_task->status ?></li>
  <li><strong><?= $this->t('.table.data') ?></strong>: <?= $this->job_task->pretty_data() ?: "ERROR" ?></li>
  <?php if (current_user()->is_mod_or_higher()) : ?>
  <li><strong><?= $this->t('.table.message') ?></strong>: <?= $this->job_task->status_message ?></li>
  <?php endif ?>
</ul>

<?php $this->contentFor('subnavbar', function(){ ?>
  <li><?= $this->linkTo($this->t(".nav.list"), ['action' => "index"]) ?></li>
  <?php if (current_user()->is_admin() && $this->job_task->status == "error") : ?>
    <li><?= $this->linkTo($this->t(".nav.restart"), ["#restart", 'id' => $this->job_task->id]) ?></li>
  <?php elseif (current_user()->is_admin() && $this->job_task->status != 'pending') : ?>
    <li><?= $this->linkTo('Force restart', ["#restart", 'id' => $this->job_task->id]) ?></li>
  <?php endif ?>
<?php }) ?>
