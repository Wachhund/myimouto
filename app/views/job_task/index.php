<h4><?= $this->t('.title') ?></h4>

<table width="100%" class="highlightable">
  <thead>
    <tr>
      <th width="5%"><?= $this->t('.table.id') ?></th>
      <th width="15%"><?= $this->t('.table.type') ?></th>
      <th width="10%"><?= $this->t('.table.status') ?></th>
      <th width="40%"><?= $this->t('.table.data') ?></th>
      <?php if (current_user()->is_mod_or_higher()) : ?>
      <th width="30%"><?= $this->t('.table.message') ?></th>
      <?php endif ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($this->job_tasks as $job_task) : ?>
      <tr>
        <td><?= $job_task->id ?></td>
        <td><?= $this->linkTo($this->h($job_task->task_type), ['#show', 'id' => $job_task->id]) ?></td>
        <td><?= $this->h($job_task->status) ?></td>
        <td><?= $this->h($job_task->pretty_data()) ?: "ERROR" ?></td>
        <?php if (current_user()->is_mod_or_higher()) : ?>
        <td><?= $this->h($job_task->status_message) ?></td>
        <?php endif ?>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<div id="paginator">
  <?= $this->willPaginate($this->job_tasks) ?>
</div>
