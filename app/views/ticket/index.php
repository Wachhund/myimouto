<div id="ticket-index">
  <h4>Tickets<?php if (!current_user()->is_mod_or_higher()) : ?> (Your Tickets)<?php endif ?></h4>

  <?= $this->formTag(['ticket#index'], ['method' => 'get'], function() { ?>
    <fieldset style="margin-bottom: 1em;">
      <legend>Filter</legend>

      <label for="ticket_status">Status</label>
      <select id="ticket_status" name="status">
        <option value="">all</option>
        <?php foreach (Ticket::VALID_STATUSES as $status) : ?>
          <option value="<?= $this->h($status) ?>" <?php if ((string)$this->params()->status === $status) : ?>selected="selected"<?php endif ?>>
            <?= $this->h($status) ?>
          </option>
        <?php endforeach ?>
      </select>

      <label for="ticket_qtype">Type</label>
      <select id="ticket_qtype" name="qtype">
        <option value="">all</option>
        <?php foreach (Ticket::VALID_QTYPES as $qtype) : ?>
          <option value="<?= $this->h($qtype) ?>" <?php if ((string)$this->params()->qtype === $qtype) : ?>selected="selected"<?php endif ?>>
            <?= $this->h($qtype) ?>
          </option>
        <?php endforeach ?>
      </select>

      <?php if (current_user()->is_mod_or_higher()) : ?>
        <label for="ticket_creator_id">Creator ID</label>
        <input id="ticket_creator_id" name="creator_id" type="text" size="8" value="<?= $this->h($this->params()->creator_id) ?>" />
      <?php endif ?>

      <input type="submit" value="Apply" />
    </fieldset>
  <?php }) ?>

  <table width="100%" class="highlightable">
    <thead>
      <tr>
        <th width="5%">ID</th>
        <th width="10%">Type</th>
        <th width="10%">Status</th>
        <th width="12%">Creator</th>
        <?php if (current_user()->is_mod_or_higher()) : ?>
          <th width="10%">Target</th>
          <th width="12%">Claimed By</th>
        <?php endif ?>
        <th width="25%">Reason</th>
        <th width="10%">Created</th>
        <th width="6%"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->tickets as $ticket) : ?>
        <tr class="<?= $this->cycle('even', 'odd') ?>">
          <td><?= $this->linkTo('#' . (int)$ticket->id, ['action' => 'show', 'id' => $ticket->id]) ?></td>
          <td><?= $this->h($ticket->qtype) ?></td>
          <td><span class="<?= $ticket->status_class() ?>"><?= $this->h($ticket->status_label()) ?></span></td>
          <td>
            <?php try { ?>
              <?= $this->linkTo($this->h(User::find_name((int)$ticket->creator_id)), ['user#show', 'id' => $ticket->creator_id]) ?>
            <?php } catch (Exception $e) { ?>
              User #<?= (int)$ticket->creator_id ?>
            <?php } ?>
          </td>
          <?php if (current_user()->is_mod_or_higher()) : ?>
            <td>
              <?php if ($ticket->model_type && $ticket->model_id) : ?>
                <?= $this->h($ticket->model_type) ?> #<?= (int)$ticket->model_id ?>
              <?php else : ?>
                -
              <?php endif ?>
            </td>
            <td>
              <?php if ($ticket->claimant_id) : ?>
                <?php try { ?>
                  <?= $this->linkTo($this->h(User::find_name((int)$ticket->claimant_id)), ['user#show', 'id' => $ticket->claimant_id]) ?>
                <?php } catch (Exception $e) { ?>
                  User #<?= (int)$ticket->claimant_id ?>
                <?php } ?>
              <?php else : ?>
                -
              <?php endif ?>
            </td>
          <?php endif ?>
          <td><?= $this->h(mb_strimwidth((string)$ticket->reason, 0, 80, '...')) ?></td>
          <td><?= $this->h($ticket->created_at) ?></td>
          <td><?= $this->linkTo('View', ['action' => 'show', 'id' => $ticket->id]) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <?php if (!$this->tickets->size()) : ?>
    <p>No tickets found.</p>
  <?php endif ?>

  <div id="paginator">
    <?= $this->willPaginate($this->tickets) ?>
  </div>

  <div style="margin-top: 1em;">
    <?php if (current_user()->is_member_or_higher()) : ?>
      <?= $this->linkTo('Create Ticket', ['action' => 'create']) ?>
    <?php endif ?>
  </div>
</div>
