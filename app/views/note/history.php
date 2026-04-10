<table width="100%" class="row-highlight">
  <thead>
    <tr>
      <th></th>
      <th width="5%"><?= $this->t('.table.post') ?></th>
      <th width="5%"><?= $this->t('.table.note') ?></th>
      <th width="60%"><?= $this->t('.table.body') ?></th>
      <th width="10%"><?= $this->t('.table.edited') ?></th>
      <th width="10%"><?= $this->t('.table.date') ?></th>
      <th width="10%"><?= $this->t('.table.options') ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($this->notes as $note) : ?>
      <tr class="<?= $this->cycle('even', 'odd') ?>">
        <td style="background: <?= $this->id_to_color($note->post_id) ?>;"></td>
        <td><?= $this->linkTo($note->post_id, ['post#show', 'id' => $note->post_id]) ?></td>
        <td><?= $this->linkTo($note->note_id . "." . $note->version, ['note#history', 'id' => $note->note_id]) ?></td>
        <td><?= $this->h($note->body) ?> <?php if (!$note->is_active) : ?>(deleted)<?php endif ?></td>
        <td><?= $this->linkTo($this->h($note->author()), ['user#show', 'id' => $note->user_id]) ?></td>
        <td><?= date('F', strtotime($note->updated_at)) ?></td>
        <td><?= $this->linkTo($this->t('.revert'), ['note#revert', 'id' => $note->note_id, 'version' => $note->version], ['method' => 'post', 'confirm' => $this->t('.revert_confirm')]) ?></td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<div id="paginator">
  <?= $this->willPaginate($this->notes) ?>
</div>

<?= $this->partial("footer") ?>
