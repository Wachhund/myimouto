<div id="ticket-show">
  <h4>Ticket #<?= (int)$this->ticket->id ?></h4>

  <table width="100%">
    <tbody>
      <tr>
        <th width="15%">Status</th>
        <td><span class="<?= $this->ticket->status_class() ?>"><?= $this->h($this->ticket->status_label()) ?></span></td>
      </tr>
      <tr>
        <th>Type</th>
        <td><?= $this->h($this->ticket->qtype) ?></td>
      </tr>
      <tr>
        <th>Creator</th>
        <td>
          <?php try { ?>
            <?= $this->linkTo($this->h(User::find_name((int)$this->ticket->creator_id)), ['user#show', 'id' => $this->ticket->creator_id]) ?>
          <?php } catch (Exception $e) { ?>
            User #<?= (int)$this->ticket->creator_id ?>
          <?php } ?>
        </td>
      </tr>
      <?php if ($this->ticket->accused_id) : ?>
        <tr>
          <th>Accused</th>
          <td>
            <?php try { ?>
              <?= $this->linkTo($this->h(User::find_name((int)$this->ticket->accused_id)), ['user#show', 'id' => $this->ticket->accused_id]) ?>
            <?php } catch (Exception $e) { ?>
              User #<?= (int)$this->ticket->accused_id ?>
            <?php } ?>
          </td>
        </tr>
      <?php endif ?>
      <?php if ($this->ticket->model_type && $this->ticket->model_id) : ?>
        <tr>
          <th>Target</th>
          <td><?= $this->h($this->ticket->model_type) ?> #<?= (int)$this->ticket->model_id ?></td>
        </tr>
      <?php endif ?>
      <tr>
        <th>Reason</th>
        <td><?= nl2br($this->h($this->ticket->reason)) ?></td>
      </tr>
      <?php if ($this->ticket->claimant_id) : ?>
        <tr>
          <th>Claimed By</th>
          <td>
            <?php try { ?>
              <?= $this->linkTo($this->h(User::find_name((int)$this->ticket->claimant_id)), ['user#show', 'id' => $this->ticket->claimant_id]) ?>
            <?php } catch (Exception $e) { ?>
              User #<?= (int)$this->ticket->claimant_id ?>
            <?php } ?>
          </td>
        </tr>
      <?php endif ?>
      <?php if ($this->ticket->response) : ?>
        <tr>
          <th>Response</th>
          <td><?= nl2br($this->h($this->ticket->response)) ?></td>
        </tr>
      <?php endif ?>
      <tr>
        <th>Created</th>
        <td><?= $this->h($this->ticket->created_at) ?></td>
      </tr>
      <?php if ($this->ticket->updated_at) : ?>
        <tr>
          <th>Updated</th>
          <td><?= $this->h($this->ticket->updated_at) ?></td>
        </tr>
      <?php endif ?>
    </tbody>
  </table>

  <?php if (current_user()->is_mod_or_higher()) : ?>
    <div style="margin-top: 1.5em;">
      <h5>Moderation Actions</h5>

      <?php
        $can_claim = in_array((string)$this->ticket->status, [Ticket::STATUS_PENDING, Ticket::STATUS_IN_PROGRESS], true);
        $can_unclaim = (string)$this->ticket->status === Ticket::STATUS_IN_PROGRESS && $this->ticket->claimant_id;
        $can_respond = in_array((string)$this->ticket->status, [Ticket::STATUS_PENDING, Ticket::STATUS_IN_PROGRESS], true);
      ?>

      <?php if ($can_claim) : ?>
        <?= $this->formTag(['action' => 'claim', 'id' => $this->ticket->id], ['method' => 'post', 'style' => 'display:inline-block; margin-right: 0.5em;'], function() { ?>
          <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
          <?= $this->submitTag('Claim') ?>
        <?php }) ?>
      <?php endif ?>

      <?php if ($can_unclaim) : ?>
        <?= $this->formTag(['action' => 'unclaim', 'id' => $this->ticket->id], ['method' => 'post', 'style' => 'display:inline-block; margin-right: 0.5em;'], function() { ?>
          <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
          <?= $this->submitTag('Unclaim') ?>
        <?php }) ?>
      <?php endif ?>

      <?php if ($can_respond) : ?>
        <div style="margin-top: 1em;">
          <?= $this->formTag(['action' => 'update', 'id' => $this->ticket->id], ['method' => 'post'], function() { ?>
            <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>

            <table>
              <tr>
                <td><label>Response</label></td>
                <td><?= $this->textAreaTag('ticket[response]', $this->h($this->ticket->response), ['rows' => 5, 'cols' => 60]) ?></td>
              </tr>
              <tr>
                <td><label>Status</label></td>
                <td>
                  <select name="ticket[status]">
                    <option value="">-- Keep current --</option>
                    <option value="approved">Approve</option>
                    <option value="rejected">Reject</option>
                  </select>
                </td>
              </tr>
              <tr>
                <td colspan="2"><?= $this->submitTag('Update Ticket') ?></td>
              </tr>
            </table>
          <?php }) ?>
        </div>
      <?php endif ?>
    </div>
  <?php endif ?>

  <div style="margin-top: 1em;">
    <?= $this->linkTo('Back to tickets', ['action' => 'index']) ?>
  </div>
</div>
