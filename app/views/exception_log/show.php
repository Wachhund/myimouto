<h4>Exception Log Detail</h4>

<p><?= $this->linkTo('Back to list', ['exception_log#index']) ?></p>

<table class="highlightable" style="width: 100%;">
  <tbody>
    <tr>
      <th style="width: 15%; text-align: right;">ID</th>
      <td><?= (int)$this->exception_log->id ?></td>
    </tr>
    <tr>
      <th style="text-align: right;">Code</th>
      <td><code><?= $this->h($this->exception_log->code) ?></code></td>
    </tr>
    <tr>
      <th style="text-align: right;">Exception Class</th>
      <td><?= $this->h($this->exception_log->exception_class) ?></td>
    </tr>
    <tr>
      <th style="text-align: right;">Message</th>
      <td><?= $this->h($this->exception_log->message) ?></td>
    </tr>
    <tr>
      <th style="text-align: right;">Request URI</th>
      <td><?= $this->h($this->exception_log->request_uri) ?></td>
    </tr>
    <tr>
      <th style="text-align: right;">Request Method</th>
      <td><?= $this->h($this->exception_log->request_method) ?></td>
    </tr>
    <tr>
      <th style="text-align: right;">IP Address</th>
      <td><?= $this->h($this->exception_log->ip_address) ?></td>
    </tr>
    <tr>
      <th style="text-align: right;">User</th>
      <td>
        <?php if ($this->exception_log->user_id) : ?>
          <?= $this->linkTo(
            $this->h(User::find_name((int)$this->exception_log->user_id)),
            ['controller' => 'user', 'action' => 'show', 'id' => $this->exception_log->user_id]
          ) ?>
        <?php else : ?>
          -
        <?php endif ?>
      </td>
    </tr>
    <tr>
      <th style="text-align: right;">Version</th>
      <td><?= $this->h($this->exception_log->version) ?></td>
    </tr>
    <tr>
      <th style="text-align: right;">Created At</th>
      <td><?= $this->h($this->exception_log->created_at) ?></td>
    </tr>
    <?php $extra = $this->exception_log->parsed_extra_data(); ?>
    <?php if (!empty($extra)) : ?>
      <tr>
        <th style="text-align: right;">Extra Data</th>
        <td><pre style="white-space: pre-wrap;"><?= $this->h(json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></td>
      </tr>
    <?php endif ?>
    <tr>
      <th style="text-align: right;">Backtrace</th>
      <td><pre style="white-space: pre-wrap; font-size: 0.85em;"><?= $this->h($this->exception_log->backtrace) ?></pre></td>
    </tr>
  </tbody>
</table>
