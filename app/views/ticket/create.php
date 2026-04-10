<div id="ticket-create">
  <h4>Create Ticket</h4>

  <?= $this->formTag(['action' => 'create'], ['method' => 'post'], function () { ?>
    <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>

    <table>
      <tr>
        <td width="15%"><label>Type</label></td>
        <td>
          <select name="ticket[qtype]">
            <?php foreach (Ticket::VALID_QTYPES as $qtype) : ?>
              <option value="<?= $this->h($qtype) ?>" <?php if ((string) $this->ticket->qtype === $qtype) : ?>selected<?php endif ?>>
                <?= $this->h(ucfirst($qtype)) ?>
              </option>
            <?php endforeach ?>
          </select>
        </td>
      </tr>
      <tr>
        <td><label>Target Type</label></td>
        <td><?= $this->textFieldTag('ticket[model_type]', $this->h($this->ticket->model_type), ['size' => 30, 'placeholder' => 'e.g. Post, Comment']) ?></td>
      </tr>
      <tr>
        <td><label>Target ID</label></td>
        <td><?= $this->textFieldTag('ticket[model_id]', $this->h($this->ticket->model_id), ['size' => 10]) ?></td>
      </tr>
      <tr>
        <td><label>Accused User ID</label></td>
        <td><?= $this->textFieldTag('ticket[accused_id]', '', ['size' => 10, 'placeholder' => 'optional']) ?></td>
      </tr>
      <tr>
        <td><label>Reason</label></td>
        <td><?= $this->textAreaTag('ticket[reason]', '', ['rows' => 8, 'cols' => 60]) ?></td>
      </tr>
      <tr>
        <td colspan="2">
          <?= $this->submitTag('Create Ticket') ?>
          <?= $this->linkTo('Cancel', ['action' => 'index']) ?>
        </td>
      </tr>
    </table>
  <?php }) ?>
</div>
